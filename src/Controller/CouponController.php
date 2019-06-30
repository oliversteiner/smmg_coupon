<?php

namespace Drupal\smmg_coupon\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\smmg_coupon\Utility\CouponTrait;
use Exception;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CouponController extends ControllerBase
{
  use CouponTrait;

  /**
   * @return string
   */
  public static function getModuleName(): string
  {
    return 'smmg_coupon';
  }

  /**
   * @param $coupon_order_nid
   * @param null $token
   * @param string $output_mode
   * @return array|bool
   */
  public function sandboxEmail(
    $coupon_order_nid,
    $token = null,
    $output_mode = 'html'
  ) {
    $build = false;

    // Get Content
    try {
      $data = self::couponVariables($coupon_order_nid, $token);
    } catch (Exception $e) {
    }
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

  /**
   * @param $coupon_order_nid
   * @param $token
   * @param null $member_nid
   * @return array
   * @throws Exception
   */
  public function thankYouPage(
    $coupon_order_nid,
    $token,
    $member_nid = null
  ): array {
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

    $templates = self::getTemplates();
    $template = file_get_contents($templates['thank_you']);
    $build = [
      'description' => [
        '#type' => 'inline_template',
        '#template' => $template,
        '#attached' => ['library' => ['smmg_coupon/smmg_coupon.main']],
        '#context' => self::couponVariables(
          $coupon_order_nid,
          $member_nid,
          $token
        ),
      ],
    ];
    return $build;
  }

  public function sandboxSendEmail(
    $coupon_order_nid,
    $token = null,
    $output_mode = 'html'
  ) {
    $build = $this->sandboxEmail(
      $coupon_order_nid,
      $token = null,
      $output_mode = 'html'
    );

  //  self::sendNotivicationMailNewCoupon($coupon_order_nid, $token);

    return $build;
  }

  /**
   * @param $nid
   * @param $token
   */
  public function sendEmail($nid, $token): void
  {
    try {
      self::sendNotificationMail($nid, $token);
    } catch (Exception $e) {
    }
  }
}
