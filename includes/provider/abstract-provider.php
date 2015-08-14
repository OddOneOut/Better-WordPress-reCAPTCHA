<?php

/**
 * Copyright (c) 2015 Khang Minh <contact@betterwp.net>
 * @license http://www.gnu.org/licenses/gpl.html GNU GENERAL PUBLIC LICENSE VERSION 3.0 OR LATER
 */

/**
 * @author Khang Minh <contact@betterwp.net>
 * @since 2.0.0
 * @package BWP reCAPTCHA
 */
abstract class BWP_Recaptcha_Provider
{
	protected $options;

	protected $domain;

	public function __construct(array $options, $domain)
	{
		$this->options = $options;
		$this->domain  = $domain;
	}

	public static function create(BWP_RECAPTCHA $plugin)
    {
		$options = $plugin->options;
		$domain  = $plugin->domain;

		$providerOptions = array(
			'site_key'                 => $options['input_pubkey'],
			'secret_key'               => $options['input_prikey'],
			'theme'                    => $options['select_theme'],
			'language'                 => $options['select_lang'],
			'tabindex'                 => $options['input_tab'],
			'invalid_response_message' => $options['input_error'],
		);

		if ('yes' == $options['use_recaptcha_v1']) {
			return new BWP_Recaptcha_Provider_V1($providerOptions, $domain);
		} else {
			$providerOptions = array_merge($providerOptions, array(
				'language' => $options['select_v2_lang'],
				'theme'    => $options['select_v2_theme'],
				'size'     => $options['select_v2_size'],
				'position' => $options['select_v2_jsapi_position']
			));

			return new BWP_Recaptcha_Provider_V2($providerOptions, $domain);
		}
    }

	/**
	 * Render the recaptcha
	 *
	 * @param WP_ERROR $errors
	 */
	abstract public function renderCaptcha(WP_Error $errors = null);

	/**
	 * Verify a captcha response
	 *
	 * @param string $userResponse if null check from $_POST
	 * @return array of errors, an empty array means there are no error
	 *                          keys are actual error codes, values are
	 *                          processed error codes
	 */
	abstract public function verify($userResponse = null);

	public function getOption($key)
	{
		return isset($this->options[$key]) ? $this->options[$key] : '';
	}

	public function getDomain()
	{
		return $this->domain;
	}

	public function getErrorMessage($errorCode)
	{
		$error = $this->processError($errorCode);

		if ('invalid-response' == $error) {
			return $this->options['invalid_response_message'];
		} elseif ('invalid-keys' == $error && current_user_can('manage_options')) {
			return __('There is some problem with your reCAPTCHA API keys, '
				. 'please double check them.', $this->domain);
		} else {
			return __('Unknown error. Please contact an administrator '
				. 'for more info.', $this->domain);
		}
	}

	protected function getIpAddress()
	{
		return !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
	}

	protected function processError($errorCode)
	{
		$errorMaps = array(
			// v2 errors
			'missing-input-secret'   => 'invalid-keys',
			'invalid-input-secret'   => 'invalid-keys',
			'missing-input-response' => 'invalid-response',
			'invalid-input-response' => 'invalid-response',
			// v1 errors
			'incorrect-captcha-sol'  => 'invalid-response'
		);

		if (isset($errorMaps[$errorCode])) {
			return $errorMaps[$errorCode];
		}

		return 'unknown';
	}

	protected function processErrors(array $errorCodes)
	{
		$processedErrorCodes = [];

		foreach ($errorCodes as $code) {
			$processedErrorCodes[$code] = $this->processError($code);
		}

		return $processedErrorCodes;
	}
}