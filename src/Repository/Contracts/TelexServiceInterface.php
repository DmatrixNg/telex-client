<?php

namespace DMatrix\TelexClient\Repository\Contracts;

interface TelexServiceInterface {

    public function sendEmail($payload);
	public function sendSMS($payload);

}
