<?php


namespace Drupal\smmg_coupon\Controller;


use Drupal\Core\Controller\ControllerBase;
use Drupal\smmg_coupon\Utility\CouponTrait;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CouponController extends ControllerBase
{

    use CouponTrait;



    public function getModuleName()
    {
        return 'smmg_coupon';
    }

    public function sandboxEmail($coupon_order_nid, $token = null, $output_mode = 'html')
    {
        $build = false;

        // Get Content
        $data = self::couponVariables($coupon_order_nid, $token);
        $data['sandbox'] = true;

        $templates = self::getTemplates();


        // HTML Email
        if ($output_mode == 'html') {

            // Build HTML Content
            $template = file_get_contents($templates['email_html']);
            $build_html = [
                'description' => [
                    '#type' => 'inline_template',
                    '#template' => $template,
                    '#context' => $data,
                ],
            ];

            $build = $build_html;
        }


        // Plaintext
        if ($output_mode == 'plain') {

            // Build Plain Text Content
            $template = file_get_contents($templates['email_plain']);

            $build_plain = [
                'description' => [
                    '#type' => 'inline_template',
                    '#template' => $template,
                    '#context' => $data,
                ],
            ];

            $build = $build_plain;

        }

        return $build;
    }

    public function thankYouPage($coupon_order_nid, $token, $member_nid = null)
    {
        // Make sure you don't trust the URL to be safe! Always check for exploits.
        if ($coupon_order_nid != false && !is_numeric($coupon_order_nid)) {
            // We will just show a standard "access denied" page in this case.
            throw new AccessDeniedHttpException();
        }

        if ($member_nid != false && !is_numeric($member_nid)) {
            throw new AccessDeniedHttpException();
        }

        if ($token == false) {
            throw new AccessDeniedHttpException();
        }

        $templates = $this->getTemplates();
        $template = file_get_contents($templates['thank_you']);
        $build = [
            'description' => [
                '#type' => 'inline_template',
                '#template' => $template,
                '#attached' => ['library' => ['smmg_coupon/smmg_coupon.main']],
                '#context' => $this->couponVariables($coupon_order_nid, $member_nid, $token),
            ],
        ];
        return $build;
    }

    public function sandboxSendEmail($coupon_order_nid, $token = null, $output_mode = 'html')
    {

        $build = $this->sandboxEmail($coupon_order_nid, $token = null, $output_mode = 'html');

        self::sendNotivicationMailNewCoupon($coupon_order_nid, $token);

        return $build;
    }


}