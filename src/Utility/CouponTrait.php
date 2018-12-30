<?php


namespace Drupal\smmg_coupon\Utility;

use Drupal\node\Entity\Node;
use Drupal\small_messages\Utility\Helper;
use Drupal\smmg_coupon\Controller\CouponController;

trait CouponTrait
{

    public static function couponVariables($coupon_order_nid, $member_nid = null, $token = null)
    {
        $variables = [];

        $variables['address']['gender'] = '';
        $variables['address']['first_name'] = '';
        $variables['address']['last_name'] = '';
        $variables['address']['street_and_number'] = '';
        $variables['address']['zip_code'] = '';
        $variables['address']['city'] = '';
        $variables['address']['email'] = '';
        $variables['address']['phone'] = '';

        $variables['coupons'] = [];

        $variables['total']['number'] = 0;
        $variables['total']['amount'] = 0;

        $variables['newsletter'] = false;

        $variables['id'] = $coupon_order_nid;
        $variables['token'] = false;


        // Clean Input
        $member_nid = trim($member_nid);
        $member_nid = intval($member_nid);

        // Clean Input
        $coupon_order_nid = trim($coupon_order_nid);
        $coupon_order_nid = intval($coupon_order_nid);

        // Load Terms from Taxonomy
        $amount_list = Helper::getTermsByID('coupon_amount');
        $gender_list = Helper::getTermsByID('gender');


        // Coupon Order
        // ==============================================
        $coupon_order = Node::load($coupon_order_nid);


        if ($coupon_order && $coupon_order->bundle() == 'coupon_order') {


            // check token
            $node_token = Helper::getFieldValue($coupon_order, 'smmg_token');

            if ($token != $node_token) {
                //  throw new AccessDeniedHttpException();
            }


            // Address
            // ==============================================
            $variables['address']['gender'] = Helper::getFieldValue($coupon_order, 'gender', $gender_list);
            $variables['address']['first_name'] = Helper::getFieldValue($coupon_order, 'first_name');
            $variables['address']['last_name'] = Helper::getFieldValue($coupon_order, 'last_name');
            $variables['address']['street_and_number'] = Helper::getFieldValue($coupon_order, 'street_and_number');
            $variables['address']['zip_code'] = Helper::getFieldValue($coupon_order, 'zip_code');
            $variables['address']['city'] = Helper::getFieldValue($coupon_order, 'city');
            $variables['address']['email'] = Helper::getFieldValue($coupon_order, 'email');
            $variables['address']['phone'] = Helper::getFieldValue($coupon_order, 'phone');


            // Token
            $variables['token'] = Helper::getFieldValue($coupon_order, 'smmg_token');


            // Coupon Units
            // ==============================================
            $coupons = [];

            // Get All Coupon_unit Nids
            $coupon_arr = Helper::getFieldValue($coupon_order, 'coupon_unit', null, true);

            // load coupon_unit Nodes
            if ($coupon_arr && count($coupon_arr) > 0) {
                $i = 0;

                foreach ($coupon_arr as $nid) {

                    $coupon_unit = Node::load($nid);
                    if ($coupon_unit && $coupon_unit->bundle() == 'coupon_unit') {

                        $coupons[$i]['number'] = Helper::getFieldValue($coupon_unit, 'coupon_number');
                        $coupons[$i]['amount'] = Helper::getFieldValue($coupon_unit, 'coupon_amount', $amount_list);

                        $i++;
                    }
                }
            }

            $variables['coupons'] = $coupons;

            // Coupon Total
            // ==============================================
            $coupon_total_number = 0;
            $coupon_total_amount = 0;

            foreach ($coupons as $coupon) {

                // Total Number
                $coupon_total_number = $coupon_total_number + $coupon['number'];

                // Total Amount
                $row_total = $coupon['number'] * $coupon['amount'];
                $coupon_total_amount = $coupon_total_amount + $row_total;
            }

            $coupon_name_singular = t('Coupon');
            $coupon_name_plural = t('Coupons');
            $currency = 'SFr';

            $number_suffix = $coupon_total_number > 1 ? $coupon_name_plural : $coupon_name_singular;

            // Save Vars
            $variables['total']['number'] = $coupon_total_number;
            $variables['total']['amount'] = $coupon_total_amount;

            $variables['total']['number_suffix'] = $number_suffix;
            $variables['total']['amount_suffix'] = $currency;
        }

        // Member & Newsletter
        // ==============================================
        if ($member_nid) {

            $member = Node::load($member_nid);

            if ($member && $member->bundle() == 'member') {

                // Newsletter
                $variables['newsletter'] = Helper::getFieldValue($member, 'smmg_accept_newsletter');
            }

        }

        return $variables;
    }

    private static function sendNotivicationMailNewCoupon($coupon_order_nid, $token = null)
    {

        $templates = CouponController::getTemplates();

        $config_email_addresses = self::getEmailAddressesFromConfig();

        // load Data
        $data = self::couponVariables($coupon_order_nid, $token);

        // Data
        $first_name = $data['address']['first_name'];
        $last_name = $data['address']['last_name'];
        $email = $data['address']['email'];

        $title = t('Order Coupon');
        $email_title = "$title: $first_name $last_name";

        // HTML
        $template_html = file_get_contents($templates['email_html']);
        $build_html = [
            'description' => [
                '#type' => 'inline_template',
                '#template' => $template_html,
                '#context' => $data,
            ],
        ];

        $message_html_body = \Drupal::service('renderer')->render($build_html);

        // Plain
        $template_plain = file_get_contents($templates['email_plain']);
        $build_plain = [
            'description' => [
                '#type' => 'inline_template',
                '#template' => $template_plain,
                '#context' => $data,
            ],
        ];


        // Send to
        $email_address_from = $config_email_addresses['from'];
        $email_addresses_to = $config_email_addresses['to'];
        $email_addresses_to[] = $email;

        foreach ($email_addresses_to as $email_address_to) {

            $message_html = self::generateMessageHtml($message_html_body);

            $data['title'] = $email_title;
            $data['message_plain'] = $build_plain;
            $data['message_html'] = $message_html;
            $data['from'] = $email_address_from;
            $data['to'] = $email_address_to;

            self::sendmail($data);

        }

        return true;

    }

    static function sendmail($data)
    {
        $params['title'] = $data['title'];
        $params['message_html'] = $data['message_html'];
        $params['message_plain'] = $data['message_html'];

        $params['from'] = $data['from'];
        $to = $data['to'];


        // System
        $mailManager = \Drupal::service('plugin.manager.mail');
        $module = 'smmg_coupon';
        $key = 'EMAIL_SMTP';
        $langcode = \Drupal::currentUser()->getPreferredLangcode();
        $send = true;


        // Send
        $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);

        if ($result['result'] != TRUE) {

            $message = t('There was a problem sending your email notification to @email.', ['@email' => $to]);
            \Drupal::messenger()->addMessage($message, 'error');
            \Drupal::logger('mail-log')->error($message);
            return;
        } else {
            $message = t('An email notification has been sent to @email ', ['@email' => $to]);
            \Drupal::messenger()->addMessage($message);
            \Drupal::logger('mail-log')->notice($message);

        }

    }

    public static function generateMessageHtml($message)
    {

        // Build the HTML Parts
        $doctype = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD XHTML 1.0 Transitional //EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
        $html_start = '<html xmlns="http://www.w3.org/1999/xhtml">';
        $head = '<head></head>';
        $body_start = '<body>';
        $body_content = $message;
        $body_end = '</body>';
        $html_end = '</html>';

        // assemble all HTMl Parts
        $html_file = $doctype . $html_start . $head . $body_start . $body_content . $body_end . $html_end;

        // HTML Output
        return $html_file;
    }

    public static function getEmailAddressesFromConfig()
    {
        $email['from'] = '';
        $email['to'] = [];

        $config = \Drupal::config('smmg_coupon.settings');

        $email_from = $config->get('email_from');
        $str_multible_email_to = $config->get('email_to');

        $email_from = trim($email_from);
        $is_valid = \Drupal::service('email.validator')->isValid($email_from);

        if ($is_valid) {
            $email['from'] = $email_from;
        }


        $arr_email_to = explode(",", $str_multible_email_to);

        foreach ($arr_email_to as $email_to) {
            $email_to = trim($email_to);
            $is_valid = \Drupal::service('email.validator')->isValid($email_to);
            if ($is_valid) {
                $email['to'][] = $email_to;
            }

        }

        return $email;
    }

    public static function getTemplates()
    {
        $module = 'smmg_coupon';
        $template_names = ['thank_you', 'email_html', 'email_plain'];


        $templates = Helper::getTemplates($module, $template_names);


        return $templates;
    }

    public static function newUnitOrder($number, $amount)
    {
        $output = [
            'status' => FALSE,
            'mode' => 'save',
            'nid' => FALSE,
            'message' => '',
        ];

        $storage = \Drupal::entityTypeManager()->getStorage('node');
        $new_unit_order = $storage->create(
            [
                'type' => 'coupon_unit',
                'status' => 1, //(1 or 0): published or not
                'promote' => 0, //(1 or 0): promoted to front page
                'field_coupon_number' => $number,
                'field_coupon_amount' => $amount,
            ]);

        // Save
        $new_unit_order->save();
        $new_order_nid = $new_unit_order->id();

        // if OK
        if ($new_order_nid) {

            $message = t('Information successfully saved');
            $output['message'] = $message;
            $output['status'] = TRUE;
            $output['nid'] = $new_order_nid;
        }

        return $output;
    }

    public static function newOrder(array $data)
    {

        $coupons = [];

        // save coupon units
        for ($i = 0; $i < 10; $i++) {
            $number = $data['coupons'][$i]['number'];
            $amount = $data['coupons'][$i]['amount'];

            if ($number > 0) {
                $result = self::newUnitOrder($number, $amount);
                $coupons[$i] = $result['nid'];
            }
        }


        // Load List for origin
        $vid = 'smmg_origin';
        $origin_list = Helper::getTermsByName($vid);

        // Token
        $token = $data['token'];


        // Fieldset address
        $gender = $data['gender'];
        $first_name = $data['first_name'];
        $last_name = $data['last_name'];
        $street_and_number = $data['street_and_number'];
        $zip_code = $data['zip_code'];
        $city = $data['city'];
        $email = $data['email'];
        $phone = $data['phone'];


        if ($first_name && $last_name) {
            $title = $first_name . ' ' . $last_name;
        } else {
            $title = $email;
        }

        $output = [
            'status' => FALSE,
            'mode' => 'save',
            'nid' => FALSE,
            'message' => '',
        ];


        $storage = \Drupal::entityTypeManager()->getStorage('node');
        $new_order = $storage->create(
            [
                'type' => 'coupon_order',
                'title' => $title,
                'status' => 1, //(1 or 0): published or not
                'promote' => 0, //(1 or 0): promoted to front page
                'field_gender' => $gender,
                'field_first_name' => $first_name,
                'field_last_name' => $last_name,
                'field_phone' => $phone,
                'field_street_and_number' => $street_and_number,
                'field_zip_code' => $zip_code,
                'field_city' => $city,
                'field_email' => $email,

                // Origin
                'field_smmg_origin' => $origin_list['coupon'],

                // Token
                'field_smmg_token' => $token,

            ]);


        // coupon
        $new_order->get('field_coupon_unit')->setValue($coupons);


        // Save
        $new_order->save();
        $new_order_nid = $new_order->id();


        // if OK
        if ($new_order_nid) {

            $message = t('Coupon Order successfully saved');
            $output['message'] = $message;
            $output['status'] = TRUE;
            $output['nid'] = $new_order_nid;

            self::sendNotivicationMailNewCoupon($new_order_nid, $token);
        }


        return $output;
    }

}