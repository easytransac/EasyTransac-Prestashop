<?php

/**
 * EasyTransac notification controller.
 */
class EasyTransacNotificationModuleFrontController extends ModuleFrontController
{

	/**
	 * @see FrontController::postProcess()
	 */
	public function postProcess()
	{
		$this->module->loginit();
		$this->module->debugLog('Start Notification');
		$this->module->debugLog('Data POST', var_export($_POST, true));

		$endpoint_response = sprintf('Presta %s Module %s  -OK',
																 _PS_VERSION_,
																 $this->module->version);

		try
		{
			$response = \EasyTransac\Core\PaymentNotification::getContent(
											$_POST,
											Configuration::get('EASYTRANSAC_API_KEY'));

			if (!$response){
				throw new Exception('empty response');
			}
		}
		catch (Exception $exc)
		{
			$this->module->debugLog('Notification error', 
															$exc->getMessage());
			error_log('EasyTransac error: ' . $exc->getMessage());
			exit;
		}

		$transactionItem = $response->getContent();

		$this->module->debugLog('Notification for Tid', 
														$response->getTid());
		$this->module->create_easytransac_order_state();
		$cart = new Cart($response->getOrderId());

		if (empty($cart->id))
		{
			Logger::AddLog('EasyTransac: Unknown Cart id');
			exit;
		}
		$customer = new Customer($cart->id_customer);

		$existing_order_id = Order::getIdByCartId($response->getOrderId());
		$existing_order = new Order($existing_order_id);

		$this->module->debugLog('Notification cart id', $response->getOrderId());
		$this->module->debugLog('Notification order id from cart', $existing_order_id);
		$this->module->debugLog('Notification customer', $existing_order->id_customer);

		$this->module->debugLog('Notification customer secure_key', $customer->secure_key);

		$this->module->debugLog('Notification client ID', $response->getClient()->getId());
		$this->module->debugLog('save tid - orderId', 
														$response->getOrderId().' - '.$response->getTid());
		
		# Saves Easytransac transaction id.
		
		$this->module->setTransactionId($response->getOrderId(), $response->getTid());
		$payment_status = null;
		$payment_message = $response->getMessage();

		// 2: payment accepted, 6: canceled, 7: refunded, 8: payment error
		switch ($response->getStatus())
		{
			case 'captured':
				$payment_status = 2;
                if (empty($customer->getClient_id())) {
                    $customer->setClient_id($response->getClient()->getId());
                }
				break;

			case 'pending':
				$payment_status = $this->module->get_pending_payment_state();
				break;

			case 'refunded':
				$payment_status = 7;
				break;

			case 'failed':
			default :
				$payment_status = 8;
				break;
		}

		$this->module->debugLog(
			sprintf('Notification for OrderId : %d, Status: %s, Prestashop Status: %s',
							$response->getOrderId(),
							$response->getStatus(),
							$payment_status));

		$this->module->debugLog('Notification amount : ' . 100*$response->getAmount());

		// Checks that paid amount matches cart total.
		$paid_total = number_format($response->getAmount(), 2, '.', '');
		$cart_total = number_format($cart->getOrderTotal(true, Cart::BOTH), 2, '.', '');
		$amount_match = $paid_total === $cart_total;

		$this->module->debugLog(
				sprintf('Notification Paid total: %d, prestashop price: %d',
								$paid_total, $cart_total));

		// Useful if amount doesn't match and it's an update.
		$original_new_state = $payment_status;

		// Payment in instalments.
		$multipay = [
			'ismulti' => 'no',
		];

		if(isset($_POST['MultiplePayments']) 
			 && isset($_POST['MultiplePaymentsStatus']) ){

			$multipay = [
				'ismulti' => $_POST['MultiplePayments'],
				'status'	=> $_POST['MultiplePaymentsStatus'],
				'repeat'  => $_POST['MultiplePaymentsRepeat'],
				'count'   => $_POST['MultiplePaymentsCount'],
			];
		}

		# Whether this transaction is part of a payment in instalments.
		$is_payment_in_instalment = $multipay['ismulti'] == 'yes';

		$is_instalment_completed = false;
		if($is_payment_in_instalment){

			# Whether this transaction is the last one of
			# a payment in instalments.
			$is_instalment_completed = $multipay['status'] == 'done';

			$this->module->debugLog('Multipay: '.implode(', ', $multipay));
			$this->module->debugLog('If instalment completed: '.$is_instalment_completed);
		}

		// Transaction amount must match order amount.
		if (!$amount_match && 2 == $payment_status && ! $is_payment_in_instalment)
		{
			$payment_message = 
				$this->l('Price paid on EasyTransac is not the same as on Prestashop')
				. ' - Tid: ' . $response->getTid();

			$payment_status = 8;
			$this->module->debugLog('Notification Amount mismatch');
		}

		// Payment status for capture payment in instalment.
		if($is_payment_in_instalment && $payment_status == 2){

			$payment_message = $this->l('Payment in instalments')
					. sprintf(' %d/%d', $multipay['count'], $multipay['repeat'])
					. ' : ' . $response->getMessage();

			$payment_status = $this->module->get_split_payment_state();
			$this->module->debugLog('Notification Order set to PAYMENT IN INSTALMENTS STATE');
		}

		$this->module->debugLog('Payment status', $payment_status);

		// First order process.
		if (empty($existing_order->id) || empty($existing_order->current_state))
		{
			$total_paid_float = (float) $paid_total;
			$this->module->debugLog('Notification Total paid float: ' . $total_paid_float);
			$extra_vars = ['transaction_id' => $response->getTid()];

			$this->module->validateOrder($cart->id, $payment_status, $total_paid_float, $this->module->displayName, $payment_message, $extra_vars, null, false, $customer->secure_key);

			$this->module->debugLog('Notification Order saved');
			$this->module->debugLog('Amount', $response->getAmount() *100);

			$existing_order_id = OrderCore::getOrderByCartId($cart->id);

			# former Prestashop 1.7.0 version
			$this->module->addOrderMessage($existing_order_id, $payment_message);

			# for Prestashop >= 1.7.7
			$this->module->addTransactionMessage(
											$existing_order_id,
											$response->getTid(), 
											$payment_message,
											$response->getAmount() *100,
											$response->getStatus());

			echo $endpoint_response;
			exit;
		}

		if ($is_payment_in_instalment) {
			/**
			 * Payment in instalments process.
			 */
			$this->module->debugLog('Payment in instalments processing');

			// Last instalment.
			if($is_instalment_completed){

				$this->module->debugLog('Payment in instalments completed');

				# Complete payment order status.
				$existing_order->setCurrentState(2);

				$this->module->addOrderMessage($existing_order->id,
																			 $payment_message);

				$this->module->addTransactionMessage(
					$existing_order_id,
					$response->getTid(),
					$payment_message,
					$response->getAmount() *100,
					$response->getStatus());

				$completed_notice = $this->l('Payment in instalments completed');
				
				// Completed notice.
				$this->module->addTransactionMessage(
					$existing_order_id,
					$response->getTid(),
					$completed_notice);

			}else{
				$this->module->addOrderMessage($existing_order->id, $payment_message);

				# for Prestashop >= 1.7.7
				$this->module->addTransactionMessage(
					$existing_order_id,
					$response->getTid(), 
					$payment_message,
					$response->getAmount() *100,
					$response->getStatus());
			}
		}
		elseif (((int) $existing_order->current_state != 2 
				|| (int) $payment_status == 7)
				&& (int) $existing_order->current_state != (int) $original_new_state)
		{
			// Updating the order's state only if current state is not captured
			// or if target state is refunded
			$existing_order->setCurrentState($payment_status);

			$this->module->addOrderMessage($existing_order->id, $payment_message);

			$amount = $response->getContent()->getAmount() * 100;

			if($payment_status == 7){
				$amount = $response->getContent()->AmountRefund() * 100;
			}

			# for Prestashop >= 1.7.7
			$this->module->addTransactionMessage(
				$existing_order_id,
				$response->getTid(), 
				$payment_message,
				$amount,
				$response->getStatus());

			$this->module->debugLog('Notification : order state changed to', $payment_status);
		}
		else
		{
			$this->module->debugLog(
				'Notification : invalid target state or same state as',
				$payment_status);
		}
		$this->module->debugLog('Notification End of Script');

		echo $endpoint_response;
		exit;
	}

}
