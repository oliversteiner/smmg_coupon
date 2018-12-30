<?php

namespace Drupal\smmg_coupon\Form;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\small_messages\Utility\Helper;
use Drupal\smmg_coupon\Controller\CouponController;
use Drupal\smmg_newsletter\Controller\NewsletterController;


/**
 * Implements CouponForm form FormBase.
 *
 */
class CouponForm extends FormBase
{


    public $number_options;
    public $amount_options;
    public $currency;
    public $coupon_singular;
    public $coupon_plural;
    public $text_number;
    public $text_amount;
    public $text_add_coupons;
    public $text_total;


    /**
     *  constructor.
     */
    public function __construct()
    {

        $this->number_options = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

        // Load Coupons Amount
        $vid = 'coupon_amount';
        $this->amount_options = Helper::getTermsByID($vid);

        // Text
        $this->coupon_singular = t('Coupon');
        $this->coupon_plural = t('Coupons');
        $this->text_add_coupons = t('Add another Coupon');
        $this->text_total = t('Total');
        $this->text_number = t('Number');
        $this->text_amount = t('Amount');


        // from Config
        $config = \Drupal::config('smmg_coupon.settings');
        $this->currency = $config->get('currency');

        // Coupon Name from Settings
        $coupon_name_singular = $config->get('coupon_name_singular');
        $coupon_name_plural = $config->get('coupon_name_plural');

        if(!empty($coupon_name_singular)){
            $this->coupon_singular = $coupon_name_singular;
        }
        if(!empty($coupon_name_plural)){
            $this->coupon_plural = $coupon_name_plural;
        }


    }


    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'smmg_coupon_form';
    }


    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {

        $values = $form_state->getUserInput();


        // Spam and Bot Protection
        honeypot_add_form_protection($form, $form_state, [
            'honeypot',
            'time_restriction',
        ]);

        // JS and CSS
        $form['#attached']['library'][] = 'smmg_coupon/smmg_coupon.form';
        $form['#attached']['drupalSettings']['coupon']['numberOptions'] = $this->number_options;
        $form['#attached']['drupalSettings']['coupon']['amountOptions'] = $this->amount_options;
        $form['#attached']['drupalSettings']['coupon']['couponSingular'] = $this->coupon_singular;
        $form['#attached']['drupalSettings']['coupon']['couponPlural'] = $this->coupon_plural;

        // Disable browser HTML5 validation
        $form['#attributes']['novalidate'] = 'novalidate';


        // Coupon
        // ==============================================

        // Titel

        $form['coupon']['table'] = [
            '#type' => 'fieldset',
            '#title' => $this->coupon_plural,
            '#attributes' => ['class' => ['coupon-block']],
        ];


        // Table Header
        $form['coupon']['table']['header'] = [
            '#theme' => '',
            '#prefix' => '<div id="coupon-table-header" class="coupon-table-header">
                <span class="coupon-table-number">' . $this->text_number . '</span>
                <span class="coupon-table-times"></span>
                <span class="coupon-table-amount">' . $this->text_amount . '</span>
                <span class="coupon-table-unit"></span> 
                <span class="coupon-table-delete"></span>
                </div>',

        ];


        for ($i = 1; $i <= 10; $i++) {

            // Default

            if (empty($values['number-' . $i])) {
                $default_number = 0;
                if ($i == 1) {
                    $default_number = 1;
                }
            } else {
                $default_number = $values['number-' . $i];
            }

            if (empty($values['amount-' . $i])) {
                $default_amount = 1;
            } else {
                $default_amount = $values['amount-' . $i];
            }


            //  $default_amount = 4; // array index of options_amount
            $default_row = $i === 1 ? 'active' : 'hide';


            $form['coupon']['table']['row_' . $i] = [
                '#type' => 'fieldset',
                '#title' => $this->t(''),
                '#attributes' => ['class' => ['coupon-table-row', $default_row]],
            ];


            // Input Number and Times
            $form['coupon']['table']['row_' . $i]['number-' . $i] = [
                '#type' => 'select',
                '#title' => '',
                '#options' => $this->number_options,
                '#value' => $default_number,
                '#required' => FALSE,
                '#prefix' => '<span class="coupon-table-number">',
                '#suffix' => '</span> <span class="coupon-table-times">&times;</span>',
            ];


            // Input Amount and Unit
            $form['coupon']['table']['row_' . $i]['amount-' . $i] = [
                '#type' => 'select',
                '#title' => '',
                '#options' => $this->amount_options,
                '#value' => $default_amount,
                '#required' => FALSE,
                '#prefix' => '<span class="coupon-table-amount">',
                '#suffix' => '</span> <span class="coupon-table-unit">' . $this->currency . '</span>',
            ];

            if ($i != 1) {
                // Button Delete
                $form['coupon']['table']['row_' . $i]['delete'] = array(
                    '#theme' => 'fontawesomeicon',
                    '#tag' => 'span',
                    '#name' => 'fas fa-trash',
                    '#settings' => NULL,
                    '#transforms' => NULL,
                    '#mask' => NULL,
                    '#prefix' => '<span class="coupon-table-delete" id="delete-' . $i . '">',
                    '#suffix' => '</span>',
                );
            }

        }


        // Add more Rows
        $form['coupon']['table']['add'] = array(
            '#theme' => 'fontawesomeicon',
            '#tag' => 'span',
            '#name' => 'fas fa-plus',
            '#settings' => NULL,
            '#transforms' => NULL,
            '#mask' => NULL,
            '#prefix' => '<div class="coupon-table-add">',
            '#suffix' => '<span>' . $this->text_add_coupons . '</span></div>',
        );


        // Table Header
        $form['coupon']['table']['total'] = [
            '#theme' => '',
            '#prefix' => '<div id="coupon-table-total" class="coupon-table-total">
                <span class="coupon-table-total-total">' . $this->text_total . ':</span>
                <span class="coupon-table-total-number">0</span> 
                <span class="coupon-table-total-number-label">' . $this->coupon_plural . '</span>
                <span class="coupon-table-total-amount">0</span>
                <span class="coupon-table-total-unit">' . $this->currency . '</span>
                </div>',

        ];

        // Adresse
        // ==============================================

        $form['coupon']['postal_address'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Address'),
            '#attributes' => ['class' => ['']],
        ];

        // Anrede
        $gender_options = [0 => t('Please Chose')];

        $vid = 'gender';
        $terms = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadTree($vid);
        foreach ($terms as $term) {

            $gender_options[$term->tid] = $term->name;
        }


        $form['coupon']['postal_address']['gender'] = [
            '#type' => 'select',
            '#title' => t('Gender'),
            '#default_value' => $gender_options[0],
            '#options' => $gender_options,
            '#required' => true,
            '#prefix' => '<div class="form-group">',
            '#suffix' => '</div>',
        ];

        // Vorname
        $form['coupon']['postal_address']['first_name'] = [
            '#type' => 'textfield',
            '#title' => t('First Name'),
            '#size' => 60,
            '#maxlength' => 128,
            '#required' => true,
            '#prefix' => '<div class="form-group">',
            '#suffix' => '</div>',
        ];


        // Nachname
        $form['coupon']['postal_address']['last_name'] = [
            '#type' => 'textfield',
            '#title' => t('Last Name'),
            '#size' => 60,
            '#maxlength' => 128,
            '#required' => true,
            '#prefix' => '<div class="form-group">',
            '#suffix' => '</div>',
        ];

        // Strasse und Nr.:
        $form['coupon']['postal_address']['street_and_number'] = [
            '#type' => 'textfield',
            '#title' => t('Street and Number'),
            '#size' => 255,
            '#maxlength' => 255,
            '#required' => true,
            '#prefix' => '<div class="form-group">',
            '#suffix' => '</div>',
        ];

        // PLZ
        $form['coupon']['postal_address']['zip_code'] = [
            '#type' => 'textfield',
            '#title' => t('ZIP'),
            '#size' => 5,
            '#maxlength' => 5,
            '#required' => true,
            '#prefix' => '<div class="form-group form-group-zip-city">',
        ];

        // Ort
        $form['coupon']['postal_address']['city'] = [
            '#type' => 'textfield',
            '#title' => t('City'),
            '#size' => 30,
            '#maxlength' => 30,
            '#required' => true,
            '#suffix' => '</div>',
        ];


        // eMail
        $form['coupon']['postal_address']['email'] = [
            '#type' => 'email',
            '#title' => t('Email'),
            '#size' => 255,
            '#maxlength' => 255,
            '#required' => FALSE,
            '#prefix' => '<div class="form-group">',
            '#suffix' => '</div>',
        ];

        // Telephone
        $form['coupon']['postal_address']['phone'] = [
            '#type' => 'textfield',
            '#title' => t('Phone'),
            '#size' => 60,
            '#maxlength' => 128,
            '#required' => FALSE,
            '#prefix' => '<div class="form-group">',
            '#suffix' => '</div>',
        ];


        // Newsletter
        // ===============================================

        //  "Newsletter abonnieren"
        $form['coupon']['subscribe_newsletter'] = [
            '#title' => $this->t('I would like to receive the newsletter.'),
            '#type' => 'checkbox',
            '#default_value' => 0,
        ];


        // Submit
        // ===============================================


        $token = Crypt::randomBytes(20);
        $form['token'] = [
            '#type' => 'hidden',
            '#value' => bin2hex($token),
        ];

        $form['actions'] = [
            '#type' => 'actions',
        ];


        // Add a submit button that handles the submission of the form.
        $form['actions']['save_data'] = [
            '#type' => 'submit',
            '#value' => $this->t('Order Coupons'),
            '#allowed_tags' => ['style'],
            '#prefix' => '<div class="form-group">',
            '#suffix' => '</div>',
        ];


        return $form;
    }


    /**
     * {@inheritdoc}
     */
    public
    function validateForm(array &$form, FormStateInterface $form_state)
    {
        $values = $form_state->getValues();

        // Fieldset address
        $gender = $values['gender'];
        $first_name = $values['first_name'];
        $last_name = $values['last_name'];
        $street_and_number = $values['street_and_number'];
        $zip_code = $values['zip_code'];
        $city = $values['city'];
        $email = $values['email'];


        // Newsletter
        $subscribe_newsletter = $values['subscribe_newsletter'];

        if ($subscribe_newsletter === 1) {

            // Empty Email
            if ($email == '') {

                $form_state->setErrorByName('email',
                    $this->t('An email address is required.'));

            } else {

                $valiated_email = \Drupal::service('email.validator')
                    ->isValid($email);

                if (FALSE === $valiated_email) {
                    $form_state->setErrorByName('email',
                        $this->t('Something is wrong with this email address.'));
                }

            }
        }

        // Address

        // Gender
        $t_gender = $this->t('Gender');
        if (!$gender || empty($gender)) {
            $form_state->setErrorByName('gender',
                $this->t('Please fill in the field "@field"', ['@field' => $t_gender])
            );
        }

        // First Name
        $t_first_name = $this->t('First Name');
        if (!$first_name || empty($first_name)) {
            $form_state->setErrorByName('first_name',
                $this->t('Please fill in the field "@field"', ['@field' => $t_first_name])
            );
        }

        // Last Name
        $t_last_name = $this->t('Last Name');
        if (!$last_name || empty($last_name)) {
            $form_state->setErrorByName('last_name',
                $this->t('Please fill in the field "@field"', ['@field' => $t_last_name])
            );
        }

        // Street and Number
        $t_street_and_number = $this->t('Street and Number');
        if (!$street_and_number || empty($street_and_number)) {
            $form_state->setErrorByName('street_and_number',
                $this->t('Please fill in the field "@field"', ['@field' => $t_street_and_number])
            );
        }

        // ZIP Code
        $t_zip_code = $this->t('ZIP');
        if (!$zip_code || empty($zip_code)) {
            $form_state->setErrorByName('ZIP',
                $this->t('Please fill in the field "@field"', ['@field' => $t_zip_code])
            );
        }

        // City
        $t_city = $this->t('City');
        if (!$city || empty($city)) {
            $form_state->setErrorByName('city',
                $this->t('Please fill in the field "@field"', ['@field' => $t_city])
            );
        }


    }


    /**
     * {@inheritdoc}
     */
    public
    function submitForm(array &$form, FormStateInterface $form_state)
    {

        $values = $form_state->getValues();
        $coupons = [];

        // coupon
        for ($i = 0; $i < 10; $i++) {
            $y = $i + 1;
            $coupons[$i] = [
                'number' => $values['number-' . $y],
                'amount' => $values['amount-' . $y]
            ];
        }

        $values['coupons'] = $coupons;

        // Newsletter
        $subscribe_newsletter = $values['subscribe_newsletter'];

        // Token
        $token = $values['token'];
        $arg = ['token' => $token,];


        // Send Coupon Order
        $result = CouponController::newOrder($values);

        if ($result) {
            if ($result['status']) {
                $arg['coupon_order_nid'] = intval($result['nid']);
            } else {
                // Error on create new Coupon Order
                if ($result['message']) {

                    $this->messenger()->addMessage($result['message'], 'error');
                }
            }
        }

        // Send Newsletter Member
        if ($subscribe_newsletter == 1) {
            $result = NewsletterController::newSubscriber($values);

            if ($result) {

                if ($result['status']) {

                    $arg['member_nid'] = intval($result['nid']);

                } else {
                    // Error on create new Member
                    if ($result['message']) {
                        $this->messenger()->addMessage($result['message'], 'error');
                    }
                }
            }
        }

        // Go to  Thank You Form
        $form_state->setRedirect('smmg_coupon.coupon.thanks', $arg);


    }


}
