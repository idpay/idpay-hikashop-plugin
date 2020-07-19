<?php
/**
 * IDPay payment plugin
 *
 * @developer JMDMahdi, vispa, mnbp1371
 * @publisher IDPay
 * @package VirtueMart
 * @subpackage payment
 * @copyright (C) 2020 IDPay
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
 *
 * http://idpay.ir
 */
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Http\Http;
use Joomla\CMS\Http\HttpFactory;

class plgHikashoppaymentIdpay extends hikashopPaymentPlugin
{
  public $accepted_currencies = ['IRR'];
  public $multiple = true;
  public $name = 'idpay';
  public $doc_form = 'idpay';

  /**
   * plgHikashoppaymentIdpay constructor.
   * @param $subject
   * @param $config
   * @param Http|null $http
   */
  public function __construct(&$subject, $config, Http $http = null)
  {
    $this->http = $http ?: HttpFactory::getHttp();
    parent::__construct($subject, $config);
  }

  /**
   * @param $api_key
   * @param $sandbox
   * @return array
   */
  public function options($api_key, $sandbox)
  {
    $options = array('Content-Type' => 'application/json',
      'X-API-KEY' => $api_key,
      'X-SANDBOX' => $sandbox,
    );
    return $options;
  }

  public function onBeforeOrderCreate(&$order, &$do)
  {
    if (parent::onBeforeOrderCreate($order, $do) === true) {
      return true;
    }

    if (empty($this->payment_params->api_key)) {
      $this->app->enqueueMessage('Please check your &quot;idpay&quot; plugin configuration');
      $do = false;
    }
  }

  /**
   * @param $order
   * @param $methods
   * @param $method_id
   * @return bool|void
   * @throws Exception
   */
  public function onAfterOrderConfirm(&$order, &$methods, $method_id)
  {
    parent::onAfterOrderConfirm($order, $methods, $method_id);

    //set information for request
    $api_key = $this->payment_params->api_key;
    $sandbox = $this->payment_params->sandbox == 'no' ? 'false' : 'true';
    $desc = 'پرداخت سفارش شماره: ' . $order->order_id;
    $callback = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=checkout&task=notify&notif_payment=' . $this->name . '&tmpl=component&lang=' . $this->locale . $this->url_itemid;

    //check amount
    $amount = round($order->cart->full_total->prices[0]->price_value_with_tax, (int)$this->currency->currency_locale['int_frac_digits']);
    if (empty($amount)) {
      $msg = $this->idpay_get_failed_message(null, null, '1001');
      $this->order_log($order->order_id, $this->otherStatusMessages(1001));
      $cancel_url = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=order&task=cancel_order&order_id=' . $order->order_id . $this->url_itemid;
      $app = JFactory::getApplication();
      $app->redirect($cancel_url, $msg, 'Error');
    }

    // Customer information
    $billing = $order->cart->billing_address;
    $name = $billing->address_firstname . ' ' . $billing->address_lastname;
    $phone = $billing->address_telephone;
    $mail = $order->customer->user_email;

    //set params and send request
    $data = array('order_id' => $order->order_id, 'amount' => $amount, 'name' => $name, 'phone' => $phone, 'mail' => $mail, 'desc' => $desc, 'callback' => $callback,);
    $url = 'https://api.idpay.ir/v1.1/payment';
    $options = $this->options($api_key, $sandbox);
    $result = $this->http->post($url, json_encode($data, true), $options);
    $http_status = $result->code;
    $result = json_decode($result->body);

    //check http error
    if ($http_status != 201 || empty($result) || empty($result->id) || empty($result->link)) {
      $msg = sprintf('خطا هنگام ایجاد تراکنش. وضعیت خطا: %s - کد خطا: %s - پیام خطا: %s', $http_status, $result->error_code, $result->error_message);
      $this->order_log($order->order_id, $msg);
      $app = JFactory::getApplication();
      $cancel_url = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=order&task=cancel_order&order_id=' . $order->order_id . $this->url_itemid;
      $app->redirect($cancel_url, $msg, 'Error');
    }

    //save idpay id in db(result id)
    $this->order_log($order->order_id, "idpay_id:$result->id");

    //redirect to result
    $this->payment_params->url = $result->link;
    return $this->showPage('redirect');
  }

  /**
   * @param $statuses
   * @return bool|void
   * @throws Exception
   */
  public function onPaymentNotification(&$statuses)
  {
    $filter = JFilterInput::getInstance();
    $app = JFactory::getApplication();
    $dbOrder = $this->getOrder($_POST['order_id']);
    $this->loadPaymentParams($dbOrder);
    if (empty($this->payment_params)) {
      return false;
    }
    $this->loadOrderData($dbOrder);
    if (empty($dbOrder)) {
      echo 'Could not load any order for your notification ' . $_POST['order_id'];

      return false;
    }
    $order_id = $dbOrder->order_id;

    $url = HIKASHOP_LIVE . 'administrator/index.php?option=com_hikashop&ctrl=order&task=edit&order_id=' . $order_id;
    $order_text = "\r\n" . JText::sprintf('NOTIFICATION_OF_ORDER_ON_WEBSITE', $dbOrder->order_number, HIKASHOP_LIVE);
    $order_text .= "\r\n" . str_replace('<br/>', "\r\n", JText::sprintf('ACCESS_ORDER_WITH_LINK', $url));
    $pid = $_POST['id'];
    $porder_id = $_POST['order_id'];
    if (!empty($pid) && !empty($porder_id) && $porder_id == $order_id) {
      if ($_POST['status'] == 10) {
        $api_key = $this->payment_params->api_key;
        $sandbox = $this->payment_params->sandbox == 'no' ? 'false' : 'true';
        $price = $_POST['amount'];

        $history = new stdClass();
        $history->notified = 0;
        $history->amount = round($dbOrder->order_full_price, (int)$this->currency->currency_locale['int_frac_digits']);
        $history->data = ob_get_clean();
        $data = array('id' => $pid, 'order_id' => $order_id);
        $url = 'https://api.idpay.ir/v1.1/payment/verify';
        $options = $this->options($api_key, $sandbox);
        $result = $this->http->post($url, json_encode($data, true), $options);
        $http_status = $result->code;
        $result = json_decode($result->body);

        //check http error
        if ($http_status != 200) {
          $order_status = $this->payment_params->invalid_status;
          $email = new stdClass();
          $email->subject = JText::sprintf('NOTIFICATION_REFUSED_FOR_THE_ORDER', 'idpay') . 'invalid transaction';
          $email->body = JText::sprintf("Hello,\r\n A idpay notification was refused because it could not be verified by the idpay server (or pay cenceled)") . "\r\n\r\n" . JText::sprintf('CHECK_DOCUMENTATION', HIKASHOP_HELPURL . 'payment-idpay-error#invalidtnx');
          $msg = sprintf('خطا هنگام بررسی تراکنش. وضعیت خطا: %s - کد خطا: %s - پیام خطا: %s', $http_status, $result->error_code, $result->error_message);
          $this->modifyOrder($order_id, $order_status, NULL, $email);
          $this->order_log($order_id, $msg);
          $app->redirect(HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=order', $msg, 'Error');
        }

        $verify_status = empty($result->status) ? NULL : $result->status;
        $verify_track_id = empty($result->track_id) ? NULL : $result->track_id;
        $verify_order_id = empty($result->order_id) ? NULL : $result->order_id;
        $verify_amount = empty($result->amount) ? NULL : $result->amount;
        $card_no = empty($result->payment->card_no) ? NULL : $result->payment->card_no;
        $hashed_card_no = empty($result->payment->hashed_card_no) ? NULL : $result->payment->hashed_card_no;

        $redirect_message_type = '';
        if (empty($verify_status) || empty($verify_track_id) || empty($verify_amount) || $verify_amount != $price || $verify_status < 100) {
          $order_status = $this->payment_params->pending_status;
          $order_text = JText::sprintf('CHECK_DOCUMENTATION', HIKASHOP_HELPURL . 'payment-idpay-error#verify') . "\r\n\r\n" . $order_text;
          $msg = $this->idpay_get_failed_message($verify_track_id, $verify_order_id);
          $redirect_message_type = 'Error';
        } else {
          $order_status = $this->payment_params->verified_status;
          $msg = $this->idpay_get_success_message($verify_track_id, $verify_order_id);
        }

        $config = &hikashop_config();
        if ($config->get('order_confirmed_status', 'confirmed') == $order_status) {
          $history->notified = 1;
        }

        //Check double spending
        $idpay_id = "idpay_id:$result->id";
        $db = JFactory::getDBO();
        $sql = 'SElECT history_reason FROM ' . "#__hikashop_history" . '  WHERE history_order_id= ' . $order_id . ' AND history_reason = "' . $idpay_id . '"';
        $db->setQuery($sql);
        $db->execute();
        $exist = $db->loadObjectList();
        $exist = count($exist);
        if ($verify_order_id !== $order_id or !$exist) {
          $msg = $this->idpay_get_failed_message($verify_track_id, $order_id, 0);
          $order_status = $this->payment_params->invalid_status;
          $email = new stdClass();
          $email->subject = JText::sprintf('NOTIFICATION_REFUSED_FOR_THE_ORDER', 'idpay') . 'invalid transaction';
          $email->body = JText::sprintf("Hello,\r\n A Idpay notification was refused because it could not be verified by the idpay server (or pay cenceled)") . "\r\n\r\n" . JText::sprintf('CHECK_DOCUMENTATION', HIKASHOP_HELPURL . 'payment-idpay-error#invalidtnx');
          $action = false;
          $this->modifyOrder($order_id, $order_status, null, $email);
          //log for payment
          $this->order_log($order_id, $this->otherStatusMessages(0));
          $app->redirect(HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=order', $msg, 'Error');
        }

        //generate msg for save to db
        $msgForLog = $this->otherStatusMessages($verify_status) . "کد پیگیری :  $verify_track_id " . "شماره کارت :  $card_no " . "شماره کارت رمزنگاری شده : $hashed_card_no ";

        $email = new stdClass();
        $email->subject = JText::sprintf('PAYMENT_NOTIFICATION_FOR_ORDER', 'idpay', $order_status, $dbOrder->order_number);
        $email->body = str_replace('<br/>', "\r\n", JText::sprintf('PAYMENT_NOTIFICATION_STATUS', 'idpay', $order_status)) . ' ' . JText::sprintf('ORDER_STATUS_CHANGED', $order_status) . "\r\n\r\n" . $order_text;
        $this->modifyOrder($order_id, $order_status, $history, $email);
        $this->order_log($order_id, $msgForLog);
        $app->redirect(HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=order', $msg, $redirect_message_type);
      } else {
        //failed transaction
        $msg = $this->idpay_get_failed_message($_POST['track_id'], $_POST['order_id'], $_POST['status']);
        $order_status = $this->payment_params->invalid_status;

        $email = new stdClass();
        $email->subject = JText::sprintf('NOTIFICATION_REFUSED_FOR_THE_ORDER', 'idpay') . 'invalid transaction';
        $email->body = JText::sprintf("Hello,\r\n A Idpay notification was refused because it could not be verified by the idpay server (or pay cenceled)") . "\r\n\r\n" . JText::sprintf('CHECK_DOCUMENTATION', HIKASHOP_HELPURL . 'payment-idpay-error#invalidtnx');
        $action = false;

        $this->modifyOrder($order_id, $order_status, null, $email);
        //log for payment
        $this->order_log($order_id, $this->otherStatusMessages($_POST['status']));

        $app->redirect(HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=order', $msg, 'Error');
      }
    } else {
      $msg = 'کاربر از انجام تراکنش منصرف شده است';
      $order_status = $this->payment_params->invalid_status;
      $email = new stdClass();
      $email->subject = JText::sprintf('NOTIFICATION_REFUSED_FOR_THE_ORDER', 'idpay') . 'invalid transaction';
      $email->body = JText::sprintf("Hello,\r\n A Idpay notification was refused because it could not be verified by the idpay server (or pay cenceled)") . "\r\n\r\n" . JText::sprintf('CHECK_DOCUMENTATION', HIKASHOP_HELPURL . 'payment-idpay-error#invalidtnx');
      $action = false;
      $this->modifyOrder($order_id, $order_status, null, $email);
      $this->order_log($order_id,$msg);
      $app->redirect(HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=order', $msg, 'Error');
    }

  }

  /**
   * @param $track_id
   * @param $order_id
   * @param null $msgNumber
   * @return string
   */
  public function idpay_get_failed_message($track_id, $order_id, $msgNumber = null)
  {
    $msg = $this->otherStatusMessages($msgNumber);
    return str_replace(["{track_id}", "{order_id}"], [$track_id, $order_id], $this->payment_params->failed_message) . "<br>" . "$msg";
  }

  /**
   * @param $track_id
   * @param $order_id
   * @return string|string[]
   */
  public function idpay_get_success_message($track_id, $order_id)
  {
    return str_replace(["{track_id}", "{order_id}"], [$track_id, $order_id], $this->payment_params->success_message);
  }

  /**
   * @param $element
   */
  public function onPaymentConfiguration(&$element)
  {
    $subtask = JRequest::getCmd('subtask', '');
    parent::onPaymentConfiguration($element);
  }

  /**
   * @param $element
   * @return bool
   */
  public function onPaymentConfigurationSave(&$element)
  {
    return true;
  }

  /**
   * @param $element
   */
  public function getPaymentDefaultValues(&$element)
  {
    $element->payment_name = 'پرداخت امن با آیدی پی';
    $element->payment_description = 'پرداخت امن به وسیله کلیه کارتهای عضو شتاب با درگاه پرداخت آیدی پی';
    $element->payment_images = '';
    $element->payment_params->invalid_status = 'cancelled';
    $element->payment_params->pending_status = 'created';
    $element->payment_params->verified_status = 'confirmed';
  }

  /**
   * @param $msgNumber
   * @get status from $_POST['status]
   * @return string
   */
  public function otherStatusMessages($msgNumber = null)
  {
    switch ($msgNumber) {
      case "1":
        $msg = "پرداخت انجام نشده است";
        break;
      case "2":
        $msg = "پرداخت ناموفق بوده است";
        break;
      case "3":
        $msg = "خطا رخ داده است";
        break;
      case "3":
        $msg = "بلوکه شده";
        break;
      case "5":
        $msg = "برگشت به پرداخت کننده";
        break;
      case "6":
        $msg = "برگشت خورده سیستمی";
        break;
      case "7":
        $msg = "انصراف از پرداخت";
        break;
      case "8":
        $msg = "به درگاه پرداخت منتقل شد";
        break;
      case "10":
        $msg = "در انتظار تایید پرداخت";
        break;
      case "100":
        $msg = "پرداخت تایید شده است";
        break;
      case "101":
        $msg = "پرداخت قبلا تایید شده است";
        break;
      case "200":
        $msg = "به دریافت کننده واریز شد";
        break;
      case "0":
        $msg = "سواستفاده از تراکنش قبلی";
        break;
      case "1001":
        $msg = "واحد پول انتخاب شده پشتیبانی نمی شود.";
        $msgNumber='فاقد کد خطا';
        break;
      case null:
        $msg = "خطا دور از انتظار";
        $msgNumber = 'فاقد کد خطا';
        break;
    }

    return $msg . ' -وضعیت: ' . "$msgNumber";
  }

  /**
   * @param $order_id
   * @param $msg
   * @param null $notified
   */
  public function order_log($order_id, $msg, $notified = null)
  {
    $order = new stdClass();
    $order->order_id = $order_id;
    $order->history = new stdClass();
    $order->history->history_reason = $msg;
    $order->history->history_payment_method = $this->name;
    $order->history->history_type = 'payment';
    $orderClass = hikashop_get('class.order');
    $orderClass->save($order);

  }
}
