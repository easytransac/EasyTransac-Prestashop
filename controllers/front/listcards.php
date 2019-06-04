<?php

/**
 * EasyTransac listcards controller.
 */
class EasyTransacListcardsModuleFrontController extends ModuleFrontController
{

	/**
	 * @see FrontController::postProcess()
	 */
	public function postProcess()
	{
		if (!$this->context->customer->id)
			die;
		
		if(!Configuration::get('EASYTRANSAC_ONECLICK'))
			die(json_encode(array('status' => 0)));
		
		$this->module->loginit();
		EasyTransac\Core\Logger::getInstance()->write($this->context->customer->getClient_id());
		EasyTransac\Core\Services::getInstance()->provideAPIKey(Configuration::get('EASYTRANSAC_API_KEY'));
		$clientId = $this->context->customer->getClient_id();
		// if ($clientId == null)
		// {
		// 	die(json_encode(array("status" => "-1")));
		// }
		$customer = (new EasyTransac\Entities\Customer())->setClientId($clientId);

		$request = new EasyTransac\Requests\CreditCardsList();
		$response = $request->execute($customer);
		
		if ($response->isSuccess())
		{
			$buffer = array();
			foreach ($response->getContent()->getCreditCards() as $cc)
			{
				/* @var $cc EasyTransac\Entities\CreditCard */
				$year = substr($cc->getYear(), -2, 2);

				$buffer[] = array('Alias' => $cc->getAlias(), 'CardNumber' => $cc->getNumber(), "CardYear" => $year, "CardMonth" => $cc->getMonth());
			}
			$output = array('status' => !empty($buffer), 'packet' => $buffer);
			EasyTransac\Core\Logger::getInstance()->write($output);
			echo json_encode($output);
			die;
		}
		else
		{
			EasyTransac\Core\Logger::getInstance()->write('List Cards Error: ' . $response->getErrorCode() . ' - ' . $response->getErrorMessage());
		}
		die(json_encode(array('status' => 0)));
	}

}
