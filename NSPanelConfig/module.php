<?php

declare(strict_types=1);
	class NSPanelConfig extends IPSModule
	{
		public function Create()
		{
			//Never delete this line!
			parent::Create();

			//$this->RequireParent('{6179ED6A-FC31-413C-BB8E-1204150CF376}');
			$this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
		}

		public function Destroy()
		{
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();
		}

		public function Send(string $Text)
		{
			$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', "Buffer" => $Text]));
		}

		public function ReceiveData($JSONString)
		{
			$data = json_decode($JSONString);
			IPS_LogMessage('Device RECV', utf8_decode($data->Buffer));
		}
	}