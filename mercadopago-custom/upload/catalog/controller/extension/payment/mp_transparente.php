<?php

require_once "mercadopago.php";

class ControllerExtensionPaymentMPTransparente extends Controller {
    
	private $version = "1.0.1";
	private $versionModule = "2.3";
	private $error;
	private $order_info;
	private $message;
  
	private $special_checkouts = array('MLM', 'MLB', "MPE");
  
	private $sponsors = array(
		'MLB' => 204931135,
		'MLM' => 204962951,
		'MLA' => 204931029,
		'MCO' => 204964815,
		'MLV' => 204964612,
		'MPE' => 217176790,
		'MLC' => 204927454
	);
    
    
  
	public function index() {
        
    MercadoPago\MercadoPagoSdk::initialize();

		$data['customer_email'] = $this->customer->getEmail();
		$data['button_confirm'] = $this->language->get('button_confirm');
		$data['button_back'] 	= $this->language->get('button_back');
		$data['terms'] 			= '';
		$data['public_key'] 	= $this->config->get('mp_transparente_public_key'); // TODO: Refactor
		$data['site_id'] 		= $this->config->get('mp_transparente_country');

		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']); // Session ?

		$transaction_amount = floatval($order_info['total']) * floatval($order_info['currency_value']); // Currency Value?
		$data['amount'] 		= $transaction_amount;
		$data['actionForm'] = $order_info['store_url'] . 'index.php?route=extension/payment/mp_transparente/payment';
		$data['mp_transparente_coupon'] = $this->config->get('mp_transparente_coupon');


		if ($this->config->get('mp_transparente_coupon')) { // If has a coupon

            $labels = array('mercadopago_coupon', 'cupom_obrigatorio',
                            'campanha_nao_encontrado', 'cupom_nao_pode_ser_aplicado', 'cupom_invalido',
                            'valor_minimo_invalido', 'erro_validacao_cupom', 'aguarde', 'you_save',
                            'desconto_exclusivo', 'total_compra', 'total_desconto', 'upon_aproval',
                            'see_conditions', 'aplicar', 'remover');

            $this->populate_labels($labels, $data);
		}

		if ($this->config->get('mp_transparente_country')) {
			$data['action'] = $this->config->get('mp_transparente_country');
		}

		$this->load->model('checkout/order');
		$this->language->load('extension/payment/mp_transparente'); //TODO: Check this

		$labels = array('ccnum_placeholder', 'expiration_month_placeholder', 'expiration_year_placeholder',
                        'name_placeholder', 'doctype_placeholder', 'docnumber_placeholder',
                        'installments_placeholder', 'cardType_placeholder', 'payment_button',
                        'payment_title', 'payment_processing', 'other_card_option');

        $this->populate_labels($labels, $data);

		if ($this->config->get('mp_transparente_coupon')) {
			$data['mercadopago_coupon'] = $this->language->get('mercadopago_coupon');
		}

		$data['server'] = $_SERVER;
		$data['debug'] = $this->config->get('mp_transparente_debug');
		$data['cards'] = $this->getCards();
		$data['user_logged'] = $this->customer->isLogged();

		$view = floatval(VERSION) < 2.2 ? 'default/template/payment/' : 'extension/payment/'; # is necesary?

		$data['analytics'] = $this->setPreModuleAnalytics();

		$view_uri = $view . 'mp_transparente.tpl';

		return $this->load->view($view_uri, $data);
	}

	public function getCardIssuers() {
        
        // =========================TODO: Update this ================================
        
            $method = $this->request->get['payment_method_id'];
            $token = $this->config->get('mp_transparente_access_token');
            $url = 'https://api.mercadopago.com/v1/payment_methods/card_issuers?payment_method_id=' . $method . '&access_token=' . $token;
            $issuers = $this->callJson($url);
            echo json_encode($issuers);
        
        // ===========================================================================
    
	}

	public function coupon() {
		$coupon_id = $this->request->get['coupon_id'];

		if ($coupon_id != '') {
			$coupon_id = $coupon_id;
			$response = $this->validCoupon($coupon_id); # TODO: Check this
		} else {
			$response = array(
				'status' => 400,
				'response' => array(
					'error' => 'invalid_id',
					'message' => 'invalid id'
					)
				);
			header('Content-Type: application/json');
			echo json_encode($response);
			exit();
		}
	}

	public function validCoupon($coupon_id) {
		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		$transaction_amount = floatval($order_info['total']) * floatval($order_info['currency_value']);
		$transaction_amount = round($transaction_amount, 2);
		$access_token = $this->config->get('mp_transparente_access_token');
		$mercadopago = new MP($access_token);
		$payer_email =  $order_info['email'];

		if ($coupon_id != '') {
			$response = $mercadopago->check_discount_campaigns(
				$transaction_amount,
				$payer_email,
				$coupon_id
				);
			header( 'HTTP/1.1 200 OK' );
			header( 'Content-Type: application/json' );
			echo json_encode( $response );
		} else {
			$obj = new stdClass();
			$obj->status = 404;
			$obj->response = array(
				'message' => 'a problem has occurred',
				'error' => 'a problem has occurred',
				'status' => 404,
				'cause' => array()
				);
			header( 'HTTP/1.1 200 OK' );
			header( 'Content-Type: application/json' );
			echo json_encode( $obj );
		}
	}

	public function payment() {
        
		$params_mercadopago = $_REQUEST['mercadopago_custom'];
		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		$access_token = $this->config->get('mp_transparente_access_token');
		$mercadopago = new MP($access_token);

		if($params_mercadopago['paymentMethodId'] == ""){
			$params_mercadopago['paymentMethodId'] = $params_mercadopago['paymentMethodSelector'];
		}

		$total_price = round($order_info['total'] * $order_info['currency_value'], 2);
		if($this->config->get('mp_transparente_country') == 'MCO'){
			$total_price = $this->currency->format($order_info['total'], $order_info['currency_code'], false, false);
		}

		$payment = array();
		$payment['transaction_amount'] = $total_price;
		$payment['token'] = $params_mercadopago['token'];
		$payment['installments'] = (int) $params_mercadopago['installments'];
		$payment['payment_method_id'] = $params_mercadopago['paymentMethodId'];
		$payment['payer']['email'] = $order_info['email'];
		$payment['external_reference'] = $this->session->data['order_id'];
		$notification_url = $order_info['store_url'] . 'index.php?route=extension/payment/mp_transparente/notifications';
		$domain = $_SERVER['HTTP_HOST'];
		if (strpos($domain, "localhost") === false) {
			$payment['notification_url'] = $notification_url;
		}

		if(isset($params_mercadopago['issuer']) && $params_mercadopago['issuer'] != "" && $params_mercadopago['issuer'] > -1){
			$payment['issuer_id'] = $params_mercadopago['issuer'];
		}

		$all_products = $this->cart->getProducts();
		$items = array();
		foreach ($all_products as $product) {
			$product_price = floatval(number_format(floatval($product['price']) * floatval($order_info['currency_value']), 2));
			$items[] = array(
				"id" => $product['product_id'],
				"title" => $product['name'],
				"description" => $product['quantity'] . ' x ' . $product['name'], // string
				"quantity" => intval($product['quantity']),
				"unit_price" => $product_price, //decimal
				"picture_url" => HTTP_SERVER . 'image/' . $product['image'],
				"category_id" => $this->config->get('mp_transparente_category_id'),
				);
		}

		$is_test_user = strpos($order_info['email'], '@testuser.com');
		if (!$is_test_user) {
			$sponsor_id = $this->sponsors[$this->config->get('mp_transparente_country')];
			error_log('not test_user. sponsor_id will be sent: ' . $sponsor_id);
			$payment["sponsor_id"] = $sponsor_id;
		} else {
			error_log('test_user. sponsor_id will not be sent');
		}

		$payment['additional_info']['items'][] = $items;
		$payment['additional_info']['shipments']['receiver_address']['zip_code'] = $order_info['shipping_postcode'];
		$payment['additional_info']['shipments']['receiver_address']['street_name'] = $order_info['shipping_address_1'];
		$payment['additional_info']['shipments']['receiver_address']['street_number'] = "-";

		$customerAndCard = false;
		if($params_mercadopago['CustomerAndCard'] == 'true'){
			$customerAndCard = true;
			$payment['payer']['id'] = $this->getCustomerId();
		}

		$payment_method = $payment['payment_method_id'];
		$payment['metadata']['token'] = $params_mercadopago['token'];
		$payment['metadata']['customer_id'] = $this->getCustomerId();

		if(isset($params_mercadopago['campaign_id']) && $params_mercadopago['campaign_id'] != ""){
			$payment['coupon_amount'] = round($params_mercadopago['discount'],2);
			$payment['coupon_code'] = $params_mercadopago['coupon_code'];
			$payment['campaign_id'] = (int) $params_mercadopago['campaign_id'];
		}

		error_log("Data sent on create Payment: " . json_encode($payment));
        
        // =========================TODO: Update this ================================

            $payment = $mercadopago->create_payment($payment);
            error_log("Response Payment: " . json_encode($payment));

        // ===========================================================================

		if ($payment["status"] == 200 || $payment["status"] == 201) {
			$this->model_checkout_order->addOrderHistory($order_info['order_id'], $this->config->get('mp_transparente_order_status_id_pending'), date('d/m/Y h:i') . ' - ' .
			$payment_method);

			$this->updateOrder($payment['response']['id'],$customerAndCard);
			$this->response->redirect($this->url->link('checkout/success', '', true));
		} else {
			$this->response->redirect($this->url->link('checkout/checkout', '', true));

		}
	}

	private function getMethods($country_id) {
        
        // =========================TODO: Update this ================================
        
            $url = "https://api.mercadolibre.com/sites/" . $country_id . "/payment_methods";
            $methods = $this->callJson($url);
            return $methods;
        
        // ===========================================================================
        
	}

	public function getPaymentStatus() {
        
        // =========================TODO: Update this ================================
        
            $this->load->language('payment/mp_transparente');
            $request_type = isset($this->request->get['request_type']) ? (string) $this->request->get['request_type'] : "";
            $status = (string) $this->request->get['status'];
            if ($request_type) {
                $status = $request_type == "token" ? 'T' . $status : 'S' . $status;
            }

            $message = $this->language->get($status);
            echo json_encode(array('message' => $message));
        
        // ===========================================================================
	}

	private function callJson($url, $posts = null) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //returns the transference value like a string
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Content-Type: application/x-www-form-urlencoded')); //sets the header
		curl_setopt($ch, CURLOPT_URL, $url); //oauth API
		if (isset($posts)) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $posts);
		}
		$jsonresult = curl_exec($ch); //execute the conection
		curl_close($ch);
		$result = json_decode($jsonresult, true);
		return $result;
	}

	public function callback() {

		$this->response->redirect($this->url->link('checkout/success'));
	}

	public function notifications() {
        
        // =========================TODO: Update this ================================
            // $this->log->write();

            if (isset($this->request->get['topic']) && $this->request->get['topic'] == 'payment') {
                $this->request->get['collection_id'] = $this->request->get['id'];

                $id = $this->request->get['id'];
                $this->updateOrder($id);

                echo json_encode(200);
            } elseif(isset($this->request->get['type']) && $this->request->get['type'] == 'payment') {

                $id = $this->request->get['data_id'];
                $this->updateOrder($id);

                echo json_encode(200);
            }
        // ===========================================================================
	}

	public function getCustomerId() {
        
        // =========================TODO: Update this ================================

            $access_token = $this->config->get('mp_transparente_access_token');

            $this->load->model('checkout/order');


            $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

            $customer = array('email' => $order_info['email']);

            $search_uri = "/v1/customers/search";
            $mp = new MP($access_token);
            $response = $mp->get($search_uri, $customer);
            $response_has_results_key = array_key_exists("results", $response["response"]);
            $response_has_at_least_one_item = sizeof($response["response"]["results"]) > 0;

            if ($response_has_results_key && $response_has_at_least_one_item) {
                $customer_id = $response["response"]["results"][0]["id"];

            } else {

                $new_customer = $this->createCustomer();
                $customer_id = $new_customer["response"]["id"];

            }
            return $customer_id;
        
        // ===========================================================================
	}

	private function createCustomer() {
        // =========================TODO: Update this ================================
            $access_token = $this->config->get('mp_transparente_access_token');
            $this->load->model('checkout/order');
            $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
            $customer = array('email' => $order_info['email']);
            $uri = '/v1/customers/';
            $mp = new MP($access_token);
            $response = $mp->post($uri, $customer);
            return $response;
        // ===========================================================================
	}

	private function getCards() {
        // =========================TODO: Update this ================================
        
            $id = $this->getCustomerId();
            $retorno = null;
            $access_token = $this->config->get('mp_transparente_access_token');
            $mp = new MP($access_token);
            $cards = $mp->get("/v1/customers/" . $id . "/cards");
            if (array_key_exists("response", $cards) && sizeof($cards["response"]) > 0) {
                $this->session->data['cards'] = $cards["response"];
                $retorno = $cards["response"];
            }
            return $retorno;
        
        // ===========================================================================
	}

	private function createCard($payment) {
        // =========================TODO: Update this ================================
            $country = $this->config->get('mp_transparente_country');
            if ($country != "MPE") {
                $id = $this->getCustomerId();
                $access_token = $this->config->get('mp_transparente_access_token');
                $mp = new MP($access_token);

                $issuerId = isset($payment['issuer_id']) ? intval($payment['issuer_id']) : "";
                $paymentMethodId = isset($payment['payment_method_id']) ? $payment['payment_method_id'] : "";

                $card = $mp-> post("/v1/customers/" . $id . "/cards",
                    array(
                        "token" => $payment['metadata']['token'],
                        "issuer_id" => $issuerId,
                        "payment_method_id" => $paymentMethodId
                    )
                );
                return $card;
            }
        // ===========================================================================
	}

	private function updateOrder($id) {
        
        // =========================TODO: Update this ================================

            //$access_token = $this->config->get('mp_transparente_access_token');
        
            $url = 'https://api.mercadopago.com/v1/payments/' . $id . '?access_token=' . $access_token;
        
            
        
            $payment = MercadoPago\Payment.get($id);
        
            $order_id = $payment->external_reference;
            $this->load->model('checkout/order');
        
            //$order = $this->model_checkout_order->getOrder($order_id);
            $order_status = $payment['status']; 
        
            if ($order_status = 'approved'){ //Create card when card not exist/used
                if(isset($payment['card']) && $payment['card']['id'] == null){
                    $this->createCard($payment);
                }
            } elseif (empty($status_table[$order_status])) {
                $order_status = "other";
            }
                
        
            $status_table = array(
                "approved"      => 'mp_transparente_order_status_id_completed',
                "pending"       => 'mp_transparente_order_status_id_pending',
                "in_process"    => 'mp_transparente_order_status_id_process',
                "reject"        => 'mp_transparente_order_status_id_refunded',
                "refunded"      => 'mp_transparente_order_status_id_cancelled',
                "cancelled"     => 'mp_transparente_order_status_id_in_mediation',
                "in_mediation"  => 'mp_transparente_order_status_id_pending',
                "other"         => 'mp_transparente_order_status_id_pending'
            );
                 
                
            $payment_method_id  = $payment['payment_method_id'];
            $received_amount    = $payment['transaction_details']['net_received_amount'];
        
            $comment = date('d/m/Y h:i') . ' - ' . $payment_method_id . ' - ' . $received_amount . ' - Payment ID:' . $id;
            $status  = $this->config->get($status_table[$order_status]); 
            $this->model_checkout_order->addOrderHistory($order_id, $status, $comment); 
        
        // ===========================================================================
	}

	function _getClientId($at){
		$t = explode ( "-" , $at);
		if(count($t) > 0){
			return $t[1];
		}
		return "";
	}

  function setPreModuleAnalytics() {
      
      // =========================TODO: Update this ================================

		$query = $this->db->query("SELECT code FROM " . DB_PREFIX . "extension WHERE type = 'payment'");

        $resultModules = array();

		foreach ($query->rows as $result) {
			array_push($resultModules, $result['code']);
		}

        $return = array(
            'publicKey'=> "",
            'token'=> $this->_getClientId($this->config->get('mp_transparente_access_token')),
            'platform' => "Opencart",
            'platformVersion' => $this->versionModule,
            'moduleVersion' => $this->version,
            'payerEmail' => $this->customer->getEmail(),
            'userLogged' => $this->customer->isLogged() ? 1 : 0,
            'installedModules' => implode(', ', $resultModules),
            'additionalInfo' => ""
        );

        //error_log("===setPreModuleAnalytics====" . json_encode($return));

        return $return;
      
      // ===========================================================================
    }

	private function get_val($tag, $dest){
		$dest[$tag] = $this->language->get($tag);
	}

	#Helpers

	private function populate_labels($entries, $data){
        foreach ($entries as $entry) {
            $data[$entry] = $this->language->get($entry);
        }
	}
}
