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
		$this->module->debugLog('Data', var_export($_POST, true));
		try
		{
			$response = \EasyTransac\Core\PaymentNotification::getContent($_POST, Configuration::get('EASYTRANSAC_API_KEY'));

			if (!$response)
				throw new Exception('empty response');
		}
		catch (Exception $exc)
		{
			$this->module->debugLog('Notification error', $exc->getMessage());
			error_log('EasyTransac error: ' . $exc->getMessage());
			die;
		}

		$this->module->debugLog('Notification for Tid', $response->getTid());
		$this->module->create_easytransac_order_state();
		$cart = new Cart($response->getOrderId());

		if (empty($cart->id))
		{
			Logger::AddLog('EasyTransac: Unknown Cart id');
			die;
		}
		$customer = new Customer($cart->id_customer);

		$existing_order_id = OrderCore::getOrderByCartId($response->getOrderId());
		$existing_order = new Order($existing_order_id);

		$this->module->debugLog('Notification cart id', $response->getOrderId());
		$this->module->debugLog('Notification order id from cart', $existing_order_id);
		$this->module->debugLog('Notification customer', $existing_order->id_customer);
		$this->module->debugLog('Notification client ID', $response->getClient()->getId());
		$this->module->debugLog('save tid - orderId', $response->getOrderId().' - '.$response->getTid());
		
		$this->module->setTransactionId($response->getOrderId(), $response->getTid());
		$payment_status = null;
		$payment_message = $response->getMessage();

		// 2: payment accepted, 6: canceled, 7: refunded, 8: payment error
		switch ($response->getStatus())
		{
			case 'captured':
				$payment_status = 2;
				$customer->setClient_id($response->getClient()->getId());
				break;

			case 'pending':
				$payment_status = (int) Configuration::get('EASYTRANSAC_ID_ORDER_STATE');
				break;

			case 'refunded':
				$payment_status = 7;
				break;

			case 'failed':
			default :
				$payment_status = 8;
				break;
		}

		$this->module->debugLog('Notification for OrderId : ' . $response->getOrderId() . ', Status: ' . $response->getStatus() . ', Prestashop Status: ' . $payment_status);

		// Checks that paid amount matches cart total.
		$paid_total = number_format($response->getAmount(), 2, '.', '');
		$cart_total = number_format($cart->getOrderTotal(true, Cart::BOTH), 2, '.', '');
		$amount_match = $paid_total === $cart_total;

		$this->module->debugLog('Notification Paid total: ' . $paid_total . ' prestashop price: ' . $cart_total);

		// Useful if amount doesn't match and it's an update
		$original_new_state = $payment_status;

		if (!$amount_match && 2 == $payment_status)
		{
			$payment_message = $this->l('Price paid on EasyTransac is not the same that on Prestashop - Transaction : ') . $response->getTid();
			$payment_status = 8;
			$this->module->debugLog('Notification Amount mismatch');
		}

		if (empty($existing_order->id) || empty($existing_order->current_state))
		{
			$total_paid_float = (float) $paid_total;
			$this->module->debugLog('Notification Total paid float: ' . $total_paid_float);
			$extra_vars = ['transaction_id' => $response->getTid()];
			$this->module->validateOrder($cart->id, $payment_status, $total_paid_float, $this->module->displayName, $payment_message, $extra_vars, null, false, $customer->secure_key);
			$this->module->debugLog('Notification Order validated');
			die('Presta '._PS_VERSION_.' Module ' . $this->module->version . '-OK');
		}

		if (((int) $existing_order->current_state != 2 || (int) $payment_status == 7) && (int) $existing_order->current_state != (int) $original_new_state)
		{
			// Updating the order's state only if current state is not captured
			// or if target state is refunded
			$existing_order->setCurrentState($payment_status);

			// Add message
			if (isset($payment_message) && !empty($payment_message))
			{
				$msg = new Message();
				$message = strip_tags($payment_message, '<br>');
				$msg->message = $message;
				$msg->id_order = intval($existing_order->id);
				$msg->private = 1;
				$msg->add();
				$this->module->debugLog('message added', $message);
			}
			$this->module->debugLog('Notification : order state changed to', $payment_status);
		}
		else
		{
			$this->module->debugLog('Notification : invalid target state or same state as', $payment_status);
		}
		$this->module->debugLog('Notification End of Script');
		die('Presta '._PS_VERSION_.' Module ' . $this->module->version . '-OK');
	}

}
