<?php

namespace DMatrix\Telex\Repository\Contracts;

interface TelexServiceInterface {

    public function sendEmail($payload);
	public function sendSMS($payload);

}
