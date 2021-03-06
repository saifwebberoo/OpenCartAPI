<?php
namespace OpenCart;

class CurlRequest {
    private $url;
    private $postData = array();
    private $cookies = array();
    private $response = '';
    private $handle;
    private $sessionFile;

    private function getCookies() {
        $cookies = array();
        foreach ($this->cookies as $name=>$value) {
            $cookies[] = $name . '=' . $value;
        }
        return implode('; ', $cookies);
    }

    private function saveSession() {
        if (empty($this->sessionFile)) return;

        if (!file_exists(dirname($this->sessionFile))) {
            mkdir(dirname($this->sessionFile, 0755, true));
        }

        file_put_contents($this->sessionFile, json_encode($this->cookies));
    }

    private function restoreSession() {
        if (file_exists($this->sessionFile)) {
            $this->cookies = json_decode(file_get_contents($this->sessionFile), true);
        }
    }

    public function __construct($sessionFile) {
        $this->sessionFile = $sessionFile;
        $this->restoreSession();
    }

    public function makeRequest() {
        $this->handle = curl_init($this->url);
        curl_setopt($this->handle, CURLOPT_HEADER, true);
        curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->handle, CURLOPT_POST, true);
        curl_setopt($this->handle, CURLOPT_POSTFIELDS, http_build_query($this->postData));
        if (!empty($this->cookies)) {
            curl_setopt($this->handle, CURLOPT_COOKIE, $this->getCookies());
        }

        $this->response = curl_exec($this->handle);
        $header_size = curl_getinfo($this->handle, CURLINFO_HEADER_SIZE);
        $headers = substr($this->response, 0, $header_size);
        $this->response = substr($this->response, $header_size);

        //Save cookies
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $headers, $matches);
        $cookies = $matches[1];
        foreach ($cookies as $cookie) {
            $parts = explode('=', $cookie);
            $name = array_shift($parts);
            $value = implode('=', $parts);
            $this->cookies[$name] = $value;
        }

        curl_close($this->handle);
        $this->saveSession();
    }

    public function setUrl($url) {
        $this->url = $url;
    }

    public function setData($postData) {
        $this->postData = $postData;
    }

    public function getResponse() { return json_decode($this->response, true); }
    public function getRawResponse() { return $this->response; }
}

class Base {
    protected $oc;
    protected $curl;

    public function __construct($oc) {
        $this->oc = $oc;
        $this->curl = $oc->curl;
    }
}

class Cart extends Base {
    public function add($product, $quantity = 1, $option = array()) {
        $postData = array();
        if (is_array($product)) {
            $postData['product'] = $product;
        } else if (is_numeric($product)) {
            $postData['product_id'] = $product;
            $postData['quantity'] = $quantity;
            $postData['option'] = $option;
        } else {
            throw new InvalidProductException('Invalid product information');
        }

        $this->curl->setUrl($this->oc->getUrl('cart/add'));
        $this->curl->setData($postData);
        $this->curl->makeRequest();
        return $this->curl->getResponse();
    }

    public function edit($key, $quantity) {
        if (empty($key) || empty($quantity)) throw new InvalidDataException('Key and quantity cannot be empty for Cart->edit()');

        $postData = array(
            'key' => $key,
            'quantity' => $quantity
        );

        $this->curl->setUrl($this->oc->getUrl('cart/edit'));
        $this->curl->setData($postData);
        $this->curl->makeRequest();
        return $this->curl->getResponse();
    }

    public function remove($key) {
        if (empty($key)) throw new InvalidDataException('Key cannot be empty for Cart->remove()');

        $postData = array(
            'key' => $key
        );

        $this->curl->setUrl($this->oc->getUrl('cart/remove'));
        $this->curl->setData($postData);
        $this->curl->makeRequest();
        return $this->curl->getResponse();
    }

    public function products() {
        $this->curl->setUrl($this->oc->getUrl('cart/products'));
        $this->curl->makeRequest();
        return $this->curl->getResponse();
    }
}

class Order extends Base {
    public function add($shipping_method = '', $comment = '', $affiliate_id = '', $order_satus_id = '') {
        $postData = array(
            'shipping_method' => $shipping_method,
            'comment' => $comment,
            'affiliate_id' => $affiliate_id,
            'order_status_id' => $order_status_id
        );

        $this->curl->setUrl($this->oc->getUrl('order/add'));
        $this->curl->setData($postData);
        $this->curl->makeRequest();
        return $this->curl->getResponse();
    }

    public function edit($order_id, $shipping_method = '', $comment = '', $affiliate_id = '', $order_status_id = '') {
        if (empty($order_id)) throw new InvalidDataException("Order ID cannot be empty for Order->edit()");

        $postData = array(
            'shipping_method' => $shipping_method,
            'comment' => $comment,
            'affiliate_id' => $affiliate_id,
            'order_status_id' => $order_status_id
        );

        $this->curl->setUrl($this->oc->getUrl('order/edit&order_id='.$order_id));
        $this->curl->setData($postData);
        $this->curl->makeRequest();
        return $this->curl->getResponse();
    }

    public function delete($order_id) {
        if (empty($order_id)) throw new InvalidDataException("Order ID cannot be empty for Order->delete()");

        $this->curl->setUrl($this->oc->getUrl('order/delete&order_id='.$order_id));
        $this->curl->makeRequest();
        return $this->curl->getResponse();
    }

    public function history($order_id, $order_status_id = '', $notify = '', $append = '', $comment = '') {
        if (empty($order_id)) throw new InvalidDataException("Order ID cannot be empty for Order->edit()");

        $postData = array(
            'order_status_id' => $order_status_id,
            'notify' => $notify,
            'append' => $append,
            'comment' => $comment,
        );

        $this->curl->setUrl($this->oc->getUrl('order/history&order_id='.$order_id));
        $this->curl->setData($postData);
        $this->curl->makeRequest();
        return $this->curl->getResponse();
    }
}

class Payment extends Base {
    public function address($firstname = '', $lastname = '', $company = '', $address_1 = '', $address_2 = '', $postcode = '', $city = '', $zone_id = '', $country_id = '') {
        $postData = array(
            'firstname' => $firstname,
            'lastname' => $lastname,
            'company' => $company,
            'address_1' => $address_1,
            'address_2' => $address_2,
            'postcode' => $postcode,
            'city' => $city,
            'zone_id' => $zone_id,
            'country_id' => $country_id
        );

        $this->curl->setUrl($this->oc->getUrl('payment/address'));
        $this->curl->setData($postData);
        $this->curl->makeRequest();
        return $this->curl->getResponse();
    }

    public function methods() {
        $this->curl->setUrl($this->oc->getUrl('payment/methods'));
        $this->curl->makeRequest();
        return $this->curl->getResponse();
    }

    public function method($payment_method) {
        if (empty($payment_method)) throw new InvalidDataException("Payment method cannot be empty for Payment->method()");

        $postData = array(
            'payment_method' => $payment_method
        );

        $this->curl->setUrl($this->oc->getUrl('payment/method'));
        $this->curl->setData($postData);
        $this->curl->makeRequest();
        return $this->curl->getResponse();
    }
}

class Shipping extends Base {
    public function address($firstname = '', $lastname = '', $company = '', $address_1 = '', $address_2 = '', $postcode = '', $city = '', $zone_id = '', $country_id = '') {
        $postData = array(
            'firstname' => $firstname,
            'lastname' => $lastname,
            'company' => $company,
            'address_1' => $address_1,
            'address_2' => $address_2,
            'postcode' => $postcode,
            'city' => $city,
            'zone_id' => $zone_id,
            'country_id' => $country_id
        );

        $this->curl->setUrl($this->oc->getUrl('shipping/address'));
        $this->curl->setData($postData);
        $this->curl->makeRequest();
        return $this->curl->getResponse();
    }

    public function methods() {
        $this->curl->setUrl($this->oc->getUrl('shipping/methods'));
        $this->curl->makeRequest();
        return $this->curl->getResponse();
    }

    public function method($shipping_method) {
        if (empty($shipping_method)) throw new InvalidDataException("Shipping method cannot be empty for Shipping->method()");

        $postData = array(
            'shipping_method' => $shipping_method
        );

        $this->curl->setUrl($this->oc->getUrl('shipping/method'));
        $this->curl->setData($postData);
        $this->curl->makeRequest();
        return $this->curl->getResponse();
    }
}

class Reward extends Base {
    public function add($reward) {
        if (empty($reward)) throw new InvalidDataException("Reward cannot be empty for Reward->add()");

        $postData = array(
            'reward' => $reward
        );

        $this->curl->setUrl($this->oc->getUrl('reward'));
        $this->curl->setData($postData);
        $this->curl->makeRequest();
        return $this->curl->getResponse();
    }

    public function maximum() {
        $this->curl->setUrl($this->oc->getUrl('reward/maximum'));
        $this->curl->makeRequest();
        return $this->curl->getResponse();
    }

    public function available() {
        $this->curl->setUrl($this->oc->getUrl('reward/available'));
        $this->curl->makeRequest();
        return $this->curl->getResponse();
    }
}

class Voucher extends Base {
    public function apply($voucher) {
        if (empty($voucher)) throw new InvalidDataException("Voucher cannot be empty for Voucher->apply()");

        $postData = array(
            'voucher' => $voucher
        );

        $this->curl->setUrl($this->oc->getUrl('voucher'));
        $this->curl->setData($postData);
        $this->curl->makeRequest();
        return $this->curl->getResponse();
    }

    public function add($voucher_from_name = '', $from_email = '', $to_name = '', $to_email = '', $voucher_theme_id = '', $message = '', $amount = '') {
        if (is_array($voucher_from_name)) {
            $postData = array(
                'voucher' => $voucher_from_name
            );
        } else {
            $postData = array(
				'from_name' => $voucher_from_name,
				'from_email' => $from_email,
				'to_name' => $to_name,
				'to_email' => $to_email,
				'voucher_theme_id' => $voucher_theme_id,
				'message' => $message,
				'amount' => $amount
            );
        }

        $this->curl->setUrl($this->oc->getUrl('voucher/add'));
        $this->curl->setData($postData);
        $this->curl->makeRequest();
        return $this->curl->getResponse();
    }
}

class OpenCart {
    private $cookie;
    private $url;
    private $lastError = '';
    public $curl;
    public $cart;
    public $order;
    public $payment;
    public $reward;
    public $shipping;
    public $voucher;

    public function __construct($url, $sessionFile = '') {
        $this->url = rtrim('http://'.preg_replace('/^https?\:\/\//', '', $url), '/') . '/index.php?route=api/';
        $this->curl = new CurlRequest($sessionFile);
        $this->cart = new Cart($this);
        $this->order = new Order($this);
        $this->payment = new Payment($this);
        $this->reward = new Reward($this);
        $this->shipping = new Shipping($this);
        $this->voucher = new Voucher($this);
    }

    public function getUrl($method) { return $this->url . $method; }
    public function getCookie() { return $cookie; }
    public function getLastError() { return $this->lastError; }

    public function login($username, $password) {
        if (empty($username) || empty($password)) throw new InvalidCredentialsException("Username and password cannot be empty");

        $this->curl->setUrl($this->getUrl('login'));
        $this->curl->setData(array(
            'username' => $username,
            'password' => $password
        ));
        $this->curl->makeRequest();

        $response = $this->curl->getResponse();
        if (isset($response['success']) && isset($response['cookie'])) {
            return true;
        } else if (isset($response['error'])) {
            $this->lastError = $response['error'];
        }

        return false;
    }

    public function coupon($coupon) {
        if (empty($coupon)) throw new InvalidDataException('Coupon cannot be empty for OpenCart->coupon()');

        $postData = array(
            'coupon' => $coupon
        );

        $this->curl->setUrl($this->getUrl('coupon'));
        $this->curl->setData($postData);
        $this->curl->makeRequest();
        return $this->curl->getResponse();
    }

    public function customer($customer_id = 0, $customer_group_id = 0, $firstname = '', $lastname = '', $email = '', $telephone = '', $fax = '') {
        $postData = array(
            'customer_id' => $customer_id,
            'customer_group_id' => $customer_group_id,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'telephone' => $telephone,
            'fax' => $fax
        );

        $this->curl->setUrl($this->getUrl('customer'));
        $this->curl->setData($postData);
        $this->curl->makeRequest();
        return $this->curl->getResponse();
    }
}

class InvalidCredentialsException extends \Exception {}
class InvalidDataException extends \Exception {}
class InvalidProductException extends \Exception {}
