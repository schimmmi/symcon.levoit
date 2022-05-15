<?php

/**
 * Class Levoit
 * Connection to veSync API for fetching Levoit Air Purifiers live data.
 *
 * @version     1.0
 * @category    Symcon
 * @package     symcon.levoit
 * @author      Juergen Schilling <juergen_schilling@web.de>
 * @link        https://github.com/schimmmi/symcon.levoit
 *
 */

declare(strict_types=1);
	class Levoit extends IPSModule
	{
        private string $email;
        private string $password;
        private string $token;
        private string $account_id;
        private string $base_url = "smartapi.vesync.com";
        private string $module_name = "Levoit";

        public array $purifiers = [];

        protected array $profile_mappings = [

        ];
        /**
         * create instance
         */
		public function Create()
		{
			//Never delete this line!
			parent::Create();

            // register public properties
            $this->RegisterPropertyString('email', 'user@email.com');
            $this->RegisterPropertyString('password', '');
            $this->RegisterPropertyInteger('interval', 1); // in minutes

            // register timer
            $this->RegisterTimer('UpdateData',
                60 * 1000,
                $this->_getPrefix() . '_Update($_IPS[\'TARGET\']);');
        }

        /**
         * destroy instance
         */
		public function Destroy()
		{
			//Never delete this line!
			parent::Destroy();
		}

        /**
         * apply changes
         */
		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();

            if (IPS_GetKernelRunlevel() == KR_READY) {
                $this->onKernelReady();
            }

        }

        /**
         * execute, when kernel is ready
         */
        protected function onKernelReady()
        {
            // update timer
            $this->SetTimerInterval('UpdateData',
                $this->ReadPropertyInteger('interval') * 60 * 1000);

            // Update data
            $this->Update();
        }

        /**
         * Read config
         */
        private function ReadConfig()
        {
            $this->email = $this->ReadPropertyString('email');
            $this->password = $this->ReadPropertyString('password');

            $this->token = $this->GetBuffer('token');
            $this->account_id = $this->GetBuffer('accountID');
        }

        /**
         * read & update tank data
         */
        public function Update()
        {
            // return if veSync API service or internet connection is not available
            if (!Sys_Ping($this->base_url, 1000)) {
                $this->_log($this->module_name, "Error: veSync API or internet connection not available!");
                exit(-1);
            }

            // read config
            $this->ReadConfig();

            // check if email and password are provided
            if (!$this->email || !$this->password) {
                return false;
            }

            // force login every request
            $this->Login();

        }

        /**
         * Login to oilfox
         */
        public function Login()
        {
            $this->_log($this->module_name, sprintf('Logging in to veSync account of %s...', $this->email));

            // login url
            $login_url = 'https://' . $this->base_url. '/cloud/v1/user/login';

            // curl options
            $curlOptions = [
                CURLOPT_TIMEOUT => 10,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'email' => $this->email,
                    'password' => md5(utf8_encode($this.$this->password)),
                    'devToken' => utf8_encode(''),
                    'userType' => '1',
                    'method' => 'login'
                ]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Connection: Keep-Alive',
                ]
            ];

            // login
            $ch = curl_init($login_url);
            curl_setopt_array($ch, $curlOptions);
            $response = curl_exec($ch);
            curl_close($ch);

            // extract token
            $json_response = json_decode($response, true);
            $this->token = isset($json_response['token']) ? $json_response['token'] : false;
            $this->account_id = isset($json_response['accountID']) ? $json_response['accountID'] : false;

            // save valid token
            if ($this->token and $this->account_id) {
                $this->SetStatus(102);
                $this->SetBuffer('token', $this->token);
                $this->SetBuffer('accountID', $this->account_id);
            } // simple error handling
            else {
                $this->SetStatus(201);
                $this->_log($this->module_name,
                    'Error: The email address or password of your veSync account is invalid!');
                exit(-1);
            }
        }

    }