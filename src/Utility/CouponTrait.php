<?php


namespace Drupal\smmg_coupon\Utility;

use Drupal\node\Entity\Node;
use Drupal\small_messages\Utility\Helper;

trait CouponTrait
{
    /**
     * {@inheritdoc}
     */
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

        // Send Email ?
        $test = false;
        $test_email_address = TRUE;

        // load Data
        $data = self::couponVariables($coupon_order_nid, $token);

        // Data
        $first_name = $data['address']['first_name'];
        $last_name = $data['address']['last_name'];
        $email = $data['address']['email'];

        $title = t('Order Coupon');
        $email_title = "$title: $first_name $last_name";


        // HTML
        $template_path = drupal_get_path('module', 'smmg_coupon') . "/templates/smmg-coupon-email.html.twig";
        $template = file_get_contents($template_path);
        $build_html = [
            'description' => [
                '#type' => 'inline_template',
                '#template' => $template,
                '#context' => $data,
            ],
        ];

        $message_html_body = \Drupal::service('renderer')->render($build_html);

        // Plain
        $template_path_plain = drupal_get_path('module', 'smmg_coupon') . "/templates/smmg-coupon-email-plain.html.twig";
        $template_plain = file_get_contents($template_path_plain);
        $build_plain = [
            'description' => [
                '#type' => 'inline_template',
                '#template' => $template_plain,
                '#context' => $data,
            ],
        ];


        // Send to
        if ($test_email_address) {

            // Development
            $email_addresses = ['oliver@mollo.ch'];
        } else {

            // Production
            $email_addresses = ['oliver@mollo.ch', 'oliver@mollo.ch', $email];
        }

        foreach ($email_addresses as $email_address) {

            $message_html = self::generateMessageHtml($message_html_body);

            if ($test) {
                //   dpm('[test] send to - ' . $email_address);

            } else {
                $data['title'] = $email_title;
                $data['message_plain'] = $build_plain;
                $data['message_html'] = $message_html;
                $data['from'] = "oliver@mollo.ch";
                $data['to'] = $email_address;


                self::sendmail($data);
            }
        }

            return true;

    }

    static function sendmail($data)
    {
        $params['title'] = $data['title'];
        $params['message_html'] = $data['message_html'];
        $params['message_plain'] = $data['message_html'];

      //   $params['message_plain'] = 'TEST Body Plain';
      //   $params['message_html'] = '<h1>TEST Body Html</h1>';

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

        dpm($result);
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
}