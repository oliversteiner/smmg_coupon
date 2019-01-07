<?php


namespace Drupal\smmg_coupon\Utility;

use Drupal\Core\Entity\Entity;
use Drupal\node\Entity\Node;
use Drupal\small_messages\Utility\Email;
use Drupal\small_messages\Utility\Helper;

trait CouponTrait
{

    public static function getGroupOptions()
    {
        $option_list = [];


        $nids = \Drupal::entityQuery('node')->condition('type', 'coupon_group')->execute();
        $nodes = Node::loadMultiple($nids);

        foreach ($nodes as $node) {
            $option_list[$node->id()] = $node->label();
        }

        return $option_list;
    }

    public static function getModuleName()
    {
        return 'smmg_coupon';
    }

    public static function couponVariables($coupon_order_nid, $member_nid = null, $token = null)
    {
        $config = \Drupal::config('smmg_coupon.settings');
        $name_singular = $config->get('coupon_name_singular');
        $amount_suffix = $config->get('suffix');

        $variables = [];
        $variables['module'] = 'Coupons';


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


            // Coupon
            // ==============================================
            $variables['group'] = Helper::getFieldValue($coupon_order, 'coupon_group');


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

            $number_suffix = $coupon_total_number > 1 ? $coupon_name_plural : $coupon_name_singular;

            // Save Vars
            $variables['total']['number'] = $coupon_total_number;
            $variables['total']['amount'] = $coupon_total_amount;

            $variables['number_suffix'] = $number_suffix;
            $variables['amount_suffix'] = $amount_suffix;


            // Title
            $variables['title'] = $name_singular . ' - ' . $variables['address']['first_name'] . ' ' . $variables['address']['last_name'];
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

    private static function sendNotificationMail($nid, $token)
    {
        $module = self::getModuleName();
        $data = self::couponVariables($nid, $token);
        $templates = self::getTemplates();

        Email::sendNotificationMail($module, $data, $templates);

    }

    static function sendmail($data)
    {
        Email::sendmail($data);
    }

    public static function generateMessageHtml($message)
    {
        return Email::generateMessageHtml($message);

    }

    public static function getEmailAddressesFromConfig()
    {
        $module = self::getModuleName();
        return Email::getEmailAddressesFromConfig($module);
    }

    public static function newCouponUnit($number, $amount)
    {
        $output = [
            'status' => FALSE,
            'mode' => 'save',
            'nid' => FALSE,
            'message' => '',
        ];
        $config = \Drupal::config('smmg_coupon.settings');
        $suffix = $config->get('suffix');

        $amount_list = Helper::getTermsByID('coupon_amount');
        $title = $number . ' × ' . $amount_list[$amount] . ' ' . $suffix;
        $node = \Drupal::entityTypeManager()->getStorage('node')->create(
            [
                'type' => 'coupon_unit',
                'status' => 0, //(1 or 0): published or not
                'promote' => 0, //(1 or 0): promoted to front page
                'title' => $title,
                'field_coupon_number' => $number,
                'field_coupon_amount' => $amount,
            ]);

        // Save
        $node->save();
        $new_order_nid = $node->id();

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
        $config = \Drupal::config('smmg_coupon.settings');
        $name_singular = $config->get('coupon_name_singular');

        $coupons = [];

        // save coupon units
        for ($i = 0; $i < 10; $i++) {
            $number = $data['coupons'][$i]['number'];
            $amount = $data['coupons'][$i]['amount'];

            if ($number > 0) {
                $result = self::newCouponUnit($number, $amount);
                $coupons[$i] = $result['nid'];
            }
        }


        // Origin
        $origin = 'Coupon';
        $origin_tid = Helper::getOrigin($origin);

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
        $coupon_group = $data['coupon_group'];

        if ($first_name && $last_name) {
            $title = $name_singular. ' - '.$first_name . ' ' . $last_name;
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
                'status' => 0, //(1 or 0): published or not
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
                'field_smmg_origin' => $origin_tid,

                // Token
                'field_smmg_token' => $token,

            ]);


        // coupon
        $new_order->get('field_coupon_unit')->setValue($coupons);
        $new_order->get('field_coupon_group')->setValue($coupon_group);


        // Save
        $new_order->save();
        $new_order_nid = $new_order->id();


        // if OK
        if ($new_order_nid) {

            $message = t('Coupon Order successfully saved');
            $output['message'] = $message;
            $output['status'] = TRUE;
            $output['nid'] = $new_order_nid;

            self::sendNotificationMail($new_order_nid, $token);
        }


        return $output;
    }

    public static function getTemplateNames()
    {
        $templates = [
            'thank_you',
            'email_html',
            'email_plain',
        ];

        return $templates;
    }

    public static function getTemplates()
    {
        $module = 'smmg_coupon';
        $template_names = self::getTemplateNames();
        $templates = Helper::getTemplates($module, $template_names);

        return $templates;
    }

}