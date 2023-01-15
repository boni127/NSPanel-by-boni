<?php

# Todo Nachricth im Screensaver anzeigen:
# text setzen notify~heading~text 
# text löschen notify 
# 
# Formular: Aktionszuweisung: beim Ändern des Toggle-Button werden nicht immer die Felder maxvalue / value wieder auf enable gesetzt
# bein anzeigen eins vorhandenen Eintrags werden die Felder ebenfalls nicht korrekt gesetzt

# es gibt neue Befehle: receive: -event,buttonPress2,screensaver,swipeLeft- / -event,buttonPress2,screensaver,bExit,2- 
# diese werden noch nicht ausgewertet
# in form.json Wertzuweisung "onChange" : "DBNSP_LoadPageColumns($id,$panelPage);"  entfernt.
# Funktion kann aus module.php ebenfalls entfernt werden, ist schon auskommentiert




declare(strict_types=1);
	class NSPanelConfig extends IPSModule
	{

		const IGN  = -1;
		const MAIN = 0;
		const FWD  = 1;
		const RWD  = 2;
		const GO   = 3;
		const UP   = 4;

		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
			$this->RegisterPropertyBoolean("PropertyVariableDebug",0); 
			$this->RegisterPropertyString('topic','nspanel_');
			$this->RegisterPropertyString('sc_dimMode','dimmode~20~100');
			$this->RegisterPropertyString('sc_timeout','timeout~15');
			$this->RegisterPropertyBoolean('sc_active',1);
			$this->RegisterPropertyString('sc','pageType~screensaver');
			$this->RegisterPropertyBoolean('sc_acceptMultiClicks',1); # reagiere auf MultiClick im ScreenSaver 
			$this->RegisterPropertyBoolean('sc_acceptSwipes',0 ); # reagiere auf Swipes im Screenserver
			$this->RegisterPropertyInteger('sc_multiClickWaitTime',500); # Wartezeit bis MultiClick ausgezählt sind
			$this->RegisterPropertyBoolean('sc_notifyActive',0);
			$this->RegisterPropertyInteger('sc_notifyHeading',0);
			$this->RegisterPropertyInteger('sc_notifyText',0);
			$this->RegisterPropertyBoolean('defaultPageActive',false); # Aktivierung des customScreenSavers
			$this->RegisterPropertyInteger('defaultPageTime',30); # Wartezeit bis zur Aktivierung des customScreenSavers: Aufruf einer speziellen Seite
			$this->RegisterPropertyInteger('defaultPage',-1); # Wartezeit bis zur Aktivierung des customScreenSavers: Aufruf einer speziellen Seite			
			$this->RegisterPropertyBoolean('option73',0);
			$this->RegisterPropertyBoolean('option0',1);
			$this->RegisterAttributeInteger('currentPage',0);   # aktuell dargestellte seite
			$this->RegisterAttributeString('panelPage','{}');   # Seitendefinition interne Nutzung
			$this->RegisterAttributeString('varAssignment','{}'); # Wertzuweisung der einzelnen Seiten, interne Nutzung
			$this->RegisterAttributeString('actionAssignment','{}'); # Wertzuweisung der einzelnen Seiten, interne Nutzung
			$this->RegisterAttributeString('registerVariable',''); # Variablen überwachen (MessageSink)
			$this->RegisterAttributeInteger('skipMessageSinkforObject',0); # Objekt-ID für die Message-Sink übersrungen wird, wenn Objekt-Änderung durch Interaktion am Display erfogt
			$this->RegisterAttributeBoolean('sc_state_active',0); # # Status des Screensavers, True wenn screensaver angezeigt wird 
			$this->RegisterPropertyString('panelPageConf','{}');   # Seitendefinition im Formular
			$this->RegisterPropertyString('panelPageValuesArray','{}'); # Wertzuweisung der einzelnen Seiten
			$this->RegisterPropertyString('panelActionValuesArray','{}'); # Aktionszuweisung der einzelnen Seiten
			$this->RegisterAttributeBoolean('ScrSaverDoubleClickActive',0); # true wenn Wartezeit für Multiklick aus Screensaver
			$this->RegisterAttributeString('ScrSaverResult',0); # Nimmt rc vom Screensaver-Exit auf
			// Relais
			$this->RegisterVariableBoolean("Power1","Power1",'~Switch');
			$this->RegisterVariableBoolean("Power2","Power2",'~Switch');
			


			// Status der Instanz speichern
			$this->RegisterAttributeBoolean("Activated",false);

			$this->RegisterTimer("Refresh",0, 'IPS_RequestAction('.$this->InstanceID.',\'RefreshDate\',true);');
			$this->RegisterTimer("ScrSaverDoubleClick",0, 'IPS_RequestAction('.$this->InstanceID.',\'ScrSaverDoubleClickTimer\',true);');
			$this->RegisterTimer("callCustomScrPage",0,'IPS_RequestAction('.$this->InstanceID.',\'callCustomScrPage\',true);');
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

			// Power-Buttons
			$this->EnableAction("Power1");
			$this->EnableAction("Power2");

			// Werte aus Konfigurations-Formular laden und für die interne Nutzung anpassen
			$panelPageDst = array ();
			$panelPageSrc = json_decode($this->ReadPropertyString("panelPageConf"),true);
			foreach ($panelPageSrc as $element) {
				if (array_key_exists('return', $element) && $element['return'] > 0) $panelPageDst[$element['id']]['return'] = $element['return'];
				if (array_key_exists('main', $element) && $element['main'] > 0) $panelPageDst[$element['id']]['main'] = $element['main'];
				$panelPageDst[$element['id']]['payload'][] =  $element['type'];
				$panelPageDst[$element['id']]['payload'][] =  $element['entry'];
			}

			// Wert aus der Zuweisungtabelle laden und für die interne Nutzung anpassen
			$varAssignmentDst = array();
			$varAssignmentSrc = json_decode($this->ReadPropertyString('panelPageValuesArray'),true);


			foreach ($varAssignmentSrc as $listEntry) {
				$cnt=0;
				foreach ($listEntry['panelPageValues'] as $pageEntry) {
					//$this->LogMessage('ObjectID: '.$pageEntry['objectId'],KL_NOTIFY);
					if (array_key_exists('objectId', $pageEntry)) $varAssignmentDst[$listEntry['panelPage']][$cnt]['objectId'] = $pageEntry['objectId'];
					if (array_key_exists('split', $pageEntry) && strlen(trim($pageEntry['split']))>0) $varAssignmentDst[$listEntry['panelPage']][$cnt]['split'] = $pageEntry['split'];
					if (array_key_exists('formatted', $pageEntry)) $varAssignmentDst[$listEntry['panelPage']][$cnt]['formatted'] = $pageEntry['formatted'];
					if (array_key_exists('resultField', $pageEntry)) $varAssignmentDst[$listEntry['panelPage']][$cnt]['resultField'] = $pageEntry['resultField'];
					$cnt++;
				 }
			}

			// Werte aus der panelActionValuesArray für die interne Nutzung anpassen
			$varActionAssignmentDst = array();
			$varActionAssignmentSrc = json_decode($this->ReadPropertyString('panelActionValuesArray'),true);			
			foreach ($varActionAssignmentSrc as $actionEntry => $actionListEntry) {
				foreach ($actionListEntry['panelActionValues'] as $actionValueKey => $actionValueEntry ) {
					$filterDefinition='';
					if (array_key_exists('filter',$actionValueEntry)) {
						if (strlen(trim($actionValueEntry['filter'])) > 0 ) {
							$filterDefinition=':'.$actionValueEntry['filter'];
						}
					}
					$varActionAssignmentDst[$actionListEntry['panelActionPage']][$actionValueEntry['result'].$filterDefinition]['action'] = $actionValueEntry['action'];
					$varActionAssignmentDst[$actionListEntry['panelActionPage']][$actionValueEntry['result'].$filterDefinition]['id'] = $actionValueEntry['actionId'];
					if ($actionValueEntry['toggle']){
					 	$varActionAssignmentDst[$actionListEntry['panelActionPage']][$actionValueEntry['result'].$filterDefinition]['toggle'] = $actionValueEntry['toggle'];
					} else {
						if ($actionValueEntry['maxstep'] > 0){ 
							$varActionAssignmentDst[$actionListEntry['panelActionPage']][$actionValueEntry['result'].$filterDefinition]['maxstep'] = $actionValueEntry['maxstep'];
						} else {
							if (strlen($actionValueEntry['value']) > 0) { # Wenn Value definiert wurde
								$varActionAssignmentDst[$actionListEntry['panelActionPage']][$actionValueEntry['result'].$filterDefinition]['value'] = $actionValueEntry['value'];
							}
						}
					}
				}
			}
			

			// Werte speichern
			$this->WriteAttributeString('panelPage',json_encode($panelPageDst));
			$this->WriteAttributeString('varAssignment', json_encode($varAssignmentDst));
			$this->WriteAttributeString('actionAssignment', json_encode($varActionAssignmentDst));

			$this->sendMqtt_CustomSend(array('pageType~pageStartup'));	

			# Screensaver aktiv, aktivere Datumsroutine
			if ($this->ReadPropertyBoolean('sc_active')) {
				$this->RefreshDate(true);
			} else {
				$this->RefreshDate(false);
			}

			// set defaultPageTime

			if ($this->ReadPropertyBoolean('defaultPageActive') && ($this->ReadPropertyInteger('defaultPage')>=0)) {
				$this->SetTimerInterval('callCustomScrPage',$this->ReadPropertyInteger('defaultPageTime')*1000);
			}

			# Status Bildschrimschoner auf inaktiv setzen:
			$this->WriteAttributeBoolean('sc_state_active',0);

			// Status der Instanz auf gespeicherten Wert setzen (beim Laden des Moduls)
			if ($this->ReadAttributeBoolean("Activated")) {
				$this->SetModuleActive(true);
				}
			else {
				$this->SetModuleActive(false);
				}
		}


		public function MessageSink($TimeStamp, $SenderID, $Message, $Data) {
			# Register und unregister der Vars erfolgt in ReceiveData(), nachdem zuvor in value2Page die Variablen auf der dargestellten
			# Seite ermittelt wurden. Die Var-Id's werden ins Attribute registerVariable geschrieben und mittels registerVariableToMessageSink registriert
			# oder wieder entfernt 
			if ($Data[1]){
				if ($SenderID == $this->ReadAttributeInteger('skipMessageSinkforObject')) { # Wenn MessageSink durch skipMessageSinkforObject ausgelöst wurdem kommt die Interaktion vom Display, ignorieren
					if ($this->ReadPropertyBoolean("PropertyVariableDebug")) $this->LogMessage('skip message for '.$this->ReadAttributeInteger('skipMessageSinkforObject') ,KL_NOTIFY);
					$this->WriteAttributeInteger('skipMessageSinkforObject',0);
				} elseif ($this->ReadPropertyBoolean('sc_notifyActive') && ($this->ReadPropertyInteger('sc_notifyHeading')==$SenderID || $this->ReadPropertyInteger('sc_notifyText')==$SenderID)) {
					$this->sendMqtt_CustomSend(array($this->generateNotifyString()));
				} else {
					if ($this->ReadPropertyBoolean("PropertyVariableDebug")) $this->LogMessage('MessageSink: sender ' . $SenderID . ' Message ' . $Message,KL_NOTIFY);
					$this->sendMqtt_CustomSend($this->Value2Page(self::GO,$this->ReadAttributeInteger('currentPage')));
				}
			}
		}

		private function registerVariableToMessageSink (bool $register ) {
			# $register == true: RegisterMessage für alle auf der aktuellen Seite dargestellen Variablen durchführen  
			# $register == false: UnregisterMeassge für alle in Attribute registerVariable vorgemerkten Varaiblen aufheben und Attribut registerVariable leeren
			$debug = $this->ReadPropertyBoolean("PropertyVariableDebug");
			$target = json_decode($this->ReadAttributeString('registerVariable'),true); // Soll-Zustand  RegisterMessage
			$current = $this->GetMessageList (); // Ist-Zustand RegisterMessage

			# Object-Id's für die Heading und Notify-Zeile des Screensavers
			# wenn register = false, dann ist der screensaver aktiv.
			if (!$register) {
				if ($this->ReadPropertyInteger('sc_notifyHeading') > 1) {
					$target[$this->ReadPropertyInteger('sc_notifyHeading')]=0;
				}
				if ($this->ReadPropertyInteger('sc_notifyText') > 1) {
					$target[$this->ReadPropertyInteger('sc_notifyText')]=0;
				}
			}
			if ($debug) {
				$this->LogMessage('object-id to register:'. implode('-',array_keys($target)),KL_NOTIFY);
				$this->LogMessage('currently registered:'. implode('-',array_keys($current)),KL_NOTIFY);
			}

			if ($register) { // RegisterMessage anpassen
				foreach(array_diff_key($target,$current) as $key => $element){
					$this->RegisterMessage($key, VM_UPDATE);
					if ($debug) $this->LogMessage('var to observe: '.$key,KL_NOTIFY);
				}
				foreach(array_diff_key($current,$target) as $key => $element){
					$this->UnregisterMessage($key, VM_UPDATE);
					if ($debug) $this->LogMessage('remove from observe: '.$key,KL_NOTIFY);
				}
			} else {
				foreach($current as $key => $element){
					$this->UnregisterMessage($key, VM_UPDATE);
					if ($debug) $this->LogMessage('remove from observe: '.$key,KL_NOTIFY);
				}
				foreach(array_diff_key($target,$current) as $key => $element){
					$this->RegisterMessage($key, VM_UPDATE);
					if ($debug) $this->LogMessage('var to observe: '.$key,KL_NOTIFY);
				}
				$this->WriteAttributeString('registerVariable',json_encode(array()));
			}
		}

		public function RequestAction($Ident, $Value) {
			#$this->LogMessage('RequestAction:'.$Ident.' - '.$Value,KL_NOTIFY);
			switch ($Ident) {
				case "Power1":
					$this->SetValue("Power1",$Value);
					if ($Value) {  
						$this->switchRelais('Power1','On');
					} else {  
						$this->switchRelais('Power1','Off');
					}  
					break;
					case "Power2":
						$this->SetValue("Power2",$Value);
						if ($Value) {  
							$this->switchRelais('Power2','On');
						} else {  
							$this->switchRelais('Power2','Off');
						}  
						break;
					case "ScrSaverDoubleClickTimer":
					$this->ScrSaverDoubleClickTimer();
					break;
				case "callCustomScrPage":
					$this->callCustomScrPage();
					break;
				case "RefreshDate":
					$this->RefreshDate($Value);
					break;
				case "Send":
					$this->Send($Value);
					break;
				case "Save":
					$this->Save($Value);
					break;
				case "Load":
					$this->Load($Value);
					break;
				case "PanelActionReset":
					$this->PanelActionReset($Value);
					break;
				case "PanelActionToggle":
					$this->PanelActionToggle($Value);
					break;
				case "LoadEntry":
					$this->LoadEntry("$Value");
					break;
				case "toggleSc_active";
					$this->toggleSc_active($Value);
					break;
				case "toggleSc_multiClick";
					$this->toggleSc_multiClick($Value);
					break;
				case "toggleSc_notifyActive";
					$this->toggleSc_notifyActive($Value);
					break;
				case "toggleDefaultPageActive";
					$this->toggleDefaultPageActive($Value);
					break;
				case "deletePagePanelPageConf";
					$this->deletePagePanelPageConf();
				case "SwapModuleStatus":
					$this->SwapModuleStatus();
					break;
			}  
		}

		private function callCustomScrPage () {
			$this->SetTimerInterval('callCustomScrPage',0);

			if ($this->ReadAttributeBoolean('sc_state_active')) { # Wenn screensaver schon aktiv ist, current-Page auf die defaultPage setzen
				if ($this->ReadPropertyBoolean("PropertyVariableDebug")) $this->LogMessage('set currentpage to defaultPage: '.$this->ReadPropertyInteger('defaultPage'),KL_NOTIFY);
				$this->WriteAttributeInteger('currentPage',$this->ReadPropertyInteger('defaultPage'));				
			} else {
				if ($this->ReadAttributeInteger('currentPage') == $this->ReadPropertyInteger('defaultPage')) {
					if ($this->ReadPropertyBoolean("PropertyVariableDebug")) $this->LogMessage('default page is already open',KL_NOTIFY);
				} else {
					if ($this->ReadPropertyBoolean("PropertyVariableDebug")) $this->LogMessage('call default page: '.$this->ReadPropertyInteger('defaultPage'),KL_NOTIFY);
					$this->sendMqtt_CustomSend($this->Value2Page(self::GO,$this->ReadPropertyInteger('defaultPage')));
					$this->registerVariableToMessageSink(true);
				}
			}

		}

		private function toggleDefaultPageActive (bool $Value) {
			if ($Value) {
				$this->UpdateFormField("defaultPage", "enabled", true);
				$this->UpdateFormField("defaultPageTime", "enabled", true);
			} else {
				$this->UpdateFormField("defaultPage", "enabled", false);
				$this->UpdateFormField("defaultPageTime", "enabled", false);
			}
		}


		private function toggleSc_active (bool $active) {
			if ($active) {
				$this->UpdateFormField("sc", "enabled", true);
			} else {
				$this->UpdateFormField("sc", "enabled", false);
			}
		}

		private function toggleSc_multiClick (bool $active) {
			if ($active) {
				$this->UpdateFormField("sc_multiClickWaitTime", "enabled", true);
			} else {
				$this->UpdateFormField("sc_multiClickWaitTime", "enabled", false);
			}
		}

		private function toggleSc_notifyActive (bool $active) {
			if ($active) {
				$this->UpdateFormField("sc_notifyText", "enabled", true);
				$this->UpdateFormField("sc_notifyHeading", "enabled", true);
			} else {
				$this->UpdateFormField("sc_notifyText", "enabled", false);
				$this->UpdateFormField("sc_notifyHeading", "enabled", false);
			}
		}

		private function deletePagePanelPageConf () {
			$this->UpdateFormField("defaultPage","value",-1);

		}
		private function RefreshDate (bool $active) {
//			$id=$this->InstanceID;
			if ($active) { # nächsten Refreshzeitpunkt
				$this->SetTimerInterval("Refresh",(60-date("s",time()))*1000);
				$now_time = date("H:i", time());
				$now_date = strftime("%A %d. %b %Y", time());
				$this->sendMqtt_CustomSend(array("time~$now_time","date~$now_date"));
			} else { # Refresh abschalten
				$this->SetTimerInterval("Refresh",0);
			}
		}


		private function ScrSaverDoubleClickTimer() {
			$this->WriteAttributeBoolean('ScrSaverDoubleClickActive',0);
			$this->LogMessage('ScrSaverDoubleClick time stopped',KL_NOTIFY);
			$this->SetTimerInterval('ScrSaverDoubleClick',0);
			$this->doPanelAction(array('','screensaver','bExit',$this->ReadAttributeString('ScrSaverResult')));
		}

		private function SendBacklog()
		{
			$data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
			$data['PacketType'] = 3;
            $data['QualityOfService'] = 0;
            $data['Retain'] = false;
            $data['Topic'] = 'cmnd/'.$this->ReadPropertyString('topic').'/BACKLOG';
			$options=array();

			#option0
			if ($this->ReadPropertyBoolean('option0')) {
				array_push($options,'SETOPTION0 1');
			} else {
				array_push($options,'SETOPTION0 0');
			}
			
			#option73
			if ($this->ReadPropertyBoolean('option73')) {
				array_push($options,'SETOPTION73 1');
			} else {
				array_push($options,'SETOPTION73 0');
			}

            $data['Payload'] = implode(';',$options);
			$this->SendDataToParent(json_encode($data, JSON_UNESCAPED_SLASHES));
			if ($this->ReadPropertyBoolean("PropertyVariableDebug")) $this->LogMessage('Backlog: '.implode(';',$options),KL_NOTIFY);
		}

		private function switchRelais(string $port, string $Value) {
			$data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
			$data['PacketType'] = 3;
            $data['QualityOfService'] = 0;
            $data['Retain'] = false;
            $data['Topic'] = 'cmnd/'.$this->ReadPropertyString('topic')."/$port";
            $data['Payload'] = $Value;
			$this->SendDataToParent(json_encode($data, JSON_UNESCAPED_SLASHES));
			if ($this->ReadPropertyBoolean("PropertyVariableDebug")) $this->LogMessage("Switch: $port to $Value",KL_NOTIFY);
		
		}
		private function Send(string $Text)
		{
			$data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
			$data['PacketType'] = 3;
            $data['QualityOfService'] = 0;
            $data['Retain'] = false;
            $data['Topic'] = 'cmnd/'.$this->ReadPropertyString('topic').'/CustomSend';
            $data['Payload'] = $Text;
			$this->SendDataToParent(json_encode($data, JSON_UNESCAPED_SLASHES));
			if ($this->ReadPropertyBoolean("PropertyVariableDebug")) $this->LogMessage('Send: '.$Text,KL_NOTIFY);
		}

		private function generateNotifyString() {
			$heading=($this->ReadPropertyInteger('sc_notifyHeading') > 1) ? GetValueFormatted($this->ReadPropertyInteger('sc_notifyHeading')) : '';
			$notify=($this->ReadPropertyInteger('sc_notifyText') > 1) ? GetValueFormatted($this->ReadPropertyInteger('sc_notifyText')) : '';
			return 'notify~'.$heading.'~'.$notify;
		}

		private function LoadEntry(string $page) {
			# Tabelle der Seiteneinträge erstellen
			$panelPage = json_decode($this->ReadAttributeString("panelPage"),true);

			# kein Parameter übergeben oder ungültige Seite 
			if (strlen(trim($page)) == 0 ) return;

			if (array_key_exists('payload',$panelPage[$page])){
				$columns = explode('~',$panelPage[$page]['payload'][1]); 
				$columnNumber=0;
				$entry=array();
				$values=array();
				foreach ($columns as $element) {
					// showPageColumns
					$entry[$columnNumber]['caption'] = "$columnNumber";
					$entry[$columnNumber]['name'] = "id$columnNumber";
					$entry[$columnNumber]['width'] = (strlen($element)*12+5).'px';
					$values[0]["id$columnNumber"] = "$element";
					$columnNumber++;
				}
				//$this->LogMessage('Select: '.json_encode($entry),KL_NOTIFY);
				$this->UpdateFormField("showPageColumns", "columns", json_encode($entry));
				$this->UpdateFormField("showPageColumns", "values", json_encode($values));
			}
		}

		private function Save() {
			$name=array();
			$save_name='backup';
			foreach (IPS_GetChildrenIDs($this->InstanceID) as $id) {
				$name[(IPS_GetObject($id))['ObjectIdent']]=$id;
			}
			//$this->LogMessage('Keys: '.implode('-',array_keys($name)),KL_NOTIFY);
			$cnt=0;

			while (array_key_exists($save_name."pages$cnt",$name) || array_key_exists($save_name."assignment$cnt",$name)) {
				$this->LogMessage("Loop $cnt",KL_NOTIFY);
				$cnt++;
			}
			$this->LogMessage("Save configuration to $save_name $cnt",KL_NOTIFY);

			$this->RegisterVariableString($save_name."pages$cnt",$save_name." $cnt: pages");
			$this->SetValue($save_name."pages$cnt",json_encode($this->ReadPropertyString('panelPageConf')));

			$this->RegisterVariableString($save_name."assignment$cnt",$save_name." $cnt: assignment");
			$this->SetValue($save_name."assignment$cnt",json_encode($this->ReadPropertyString('panelPageValuesArray')));

			$this->RegisterVariableString($save_name."action$cnt",$save_name." $cnt: action");
			$this->SetValue($save_name."action$cnt",json_encode($this->ReadPropertyString('panelActionValuesArray')));

			$this->UpdateFormField("save","enabled",false);
		}

		private function Load(string $backupname) {
			$this->LogMessage('Load: '.$backupname,KL_NOTIFY);
			if (preg_match('/backuppages\d+/',$backupname) ) {
				$this->UpdateFormField('panelPageConf','values',json_decode($this->GetValue($backupname)));
			} elseif (preg_match('/backupassignment\d+/',$backupname) ) {
				$this->UpdateFormField('panelPageValuesArray','values',json_decode($this->GetValue($backupname)));
			} elseif (preg_match('/backupaction\d+/',$backupname) ) {
				$this->UpdateFormField('panelActionValuesArray','values',json_decode($this->GetValue($backupname)));
			}
		}


		// function LoadPageColumns(string $panelKey) {
		// 	$this->LogMessage("load Values into configurator for Page $panelKey",KL_NOTIFY);
		// 	$panelPage = json_decode($this->ReadAttributeString("panelPage"),true);
		// 	$columnNumber=0;
		// 	$entry=array();
		// 	foreach (explode('~',$panelPage[$panelKey]['payload'][1]) as $pageEntryColum => $pageEntryElement) {
		// 		$entry[$columnNumber]['caption'] = "($pageEntryColum) $pageEntryElement";
		// 		$entry[$columnNumber]['value'] = "$pageEntryColum";
		// 		$columnNumber++;
		// 	}
		// 	$this->LogMessage("load Entry ".json_encode($entry),KL_NOTIFY);
		// 	$this->UpdateFormField("resultField", "options", json_encode($entry));
		// 	$this->UpdateFormField("objectId", "options", json_encode($entry));
		// }

		private function PanelActionToggle(bool $toggle) {
			//$this->LogMessage('PanelActionToggle',KL_NOTIFY);
			if ($toggle) {
				$this->UpdateFormField("value", "enabled", false);
				$this->UpdateFormField("maxstep", "enabled", false);
			} else {
				$this->UpdateFormField("value", "enabled", true);
				$this->UpdateFormField("maxstep", "enabled", true);
			}
		}

		private function PanelActionReset(string $info) {
			//$this->LogMessage($info,KL_NOTIFY);
			$this->UpdateFormField("value", "enabled", true);
			$this->UpdateFormField("maxstep", "enabled", true);
		}

		private function sendMqtt_CustomSend($payload) {
			
			if (is_array($payload)) {
				$dataMQTT['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
				$dataMQTT['PacketType'] = 3;
				$dataMQTT['QualityOfService'] = 0;
				$dataMQTT['Retain'] = false;
				$dataMQTT['Topic'] = 'cmnd/'.$this->ReadPropertyString('topic').'/CustomSend';
				foreach ($payload as $value ) {
					$dataMQTT['Payload'] = $value;
					$this->SendDataToParent(json_encode($dataMQTT, JSON_UNESCAPED_SLASHES));
					if ($this->ReadPropertyBoolean("PropertyVariableDebug")) $this->LogMessage('send:'.$dataMQTT['Topic'].'/'.$dataMQTT['Payload'],KL_NOTIFY);
				}
			}
		}

		private function Value2Page(int $changePage,int $showPage) 
		{
			# Seiten-Array anlegen
			$panelPage = json_decode($this->ReadAttributeString("panelPage"),true);
			# Wenn das array leer ist, kann keine Berechnung durchgeführt werden, unconfigured an das Panel senden
			if (count($panelPage) == 0)	{
				$this->sendMqtt_CustomSend(array('pageType~cardEntities','entityUpd~unconfigured~'));
				return;
			}
			# Array für die Seitenzahlen der aktuellen Ebene anlegen
			$subPanelPage = array ();
			$debug = $this->ReadPropertyBoolean("PropertyVariableDebug"); 
			if ($changePage == self::MAIN) { # für das Hauptmenü (MAIN) wird der Indexaufbau nicht benötigt
				$currentPage = key($panelPage);
			} elseif ($changePage == self::GO) { # für den Sprung zu einer best. Seite wird der Indexaufbau nicht benötigt
				if (array_key_exists($showPage,$panelPage)){
					$currentPage = $showPage;
				} else {
					$currentPage = key($panelPage);
					if ($debug) $this->LogMessage("Page $showPage dosen't exist",KL_NOTIFY);
				}
			} elseif ($changePage == self::UP) { 
				# Wenn key return existiert, dann dort hin springen, wenn nicht zu main springen, wenn main nicht exisitiert auf die erste Seite springen
				$currentPage = $this->ReadAttributeInteger('currentPage');
				if (array_key_exists($showPage,$panelPage) && ($showPage == $this->ReadPropertyInteger('defaultPage'))) { # Aufruf der defaultPage
					if ($debug) $this->LogMessage("call default page: ".$this->ReadPropertyInteger('defaultPage'),KL_NOTIFY);
					$currentPage = $showPage;

				} elseif (array_key_exists($showPage,$panelPage) && ($showPage != $currentPage)){ # pruefe, ob nicht die Seite selbst wieder aufgerufen wird (popupNotify) verhindere loop
					if ($debug) $this->LogMessage('call page'.$currentPage,KL_NOTIFY);
					$currentPage = $showPage;
				} elseif (array_key_exists('return',$panelPage[$currentPage])){
					if ($debug) $this->LogMessage("return to page $currentPage",KL_NOTIFY);
					$currentPage = $panelPage[$currentPage]['return'];
				} elseif (array_key_exists('main',$panelPage[$currentPage])){
					if ($debug) $this->LogMessage('return to layer '.$panelPage[$currentPage]['main'],KL_NOTIFY);
					$currentPage = $panelPage[$currentPage]['main'];
				} else {
					if ($debug) $this->LogMessage("Page $currentPage: neither 'main' nor 'return' exists, goto first Page",KL_NOTIFY);
					$currentPage = key($panelPage);
				}	
			} else {
				# Index des Sub-Menüs aufbauen
				$currentPage = $this->ReadAttributeInteger('currentPage');
				# Wenn Seite nicht vorhanden, auf erste Seite setzen
				if (!array_key_exists($currentPage,$panelPage)) {
					if ($debug) $this->LogMessage("Page $currentPage doesen't exist",KL_NOTIFY);
					$currentPage = key($panelPage);
				}
				# submenu aufbauen
				if (array_key_exists('main',$panelPage[$currentPage])) { # 
					$search_key = $panelPage[$currentPage]['main'];
					if ($debug) $this->LogMessage("search_key: $search_key",KL_NOTIFY);
					foreach ($panelPage as $key => $element) {
						if (array_key_exists('main',$element) && $element['main'] == $search_key){
							$subPanelPage[] = $key;
						}
					}
				} else {
					foreach ($panelPage as $key => $element) {
						if (!array_key_exists('main',$element) ){
							$subPanelPage[] = $key;
						}
					}
				}

				# Array positionieren
				$subPanelPageLength=count($subPanelPage);
				$subPanelPagePos=array_flip($subPanelPage)[$currentPage];
				if ($debug) $this->LogMessage("current Page $currentPage, subPanelPage: ".implode(':',$subPanelPage).' -  Length ('.$subPanelPageLength.') Pos is '.$subPanelPagePos,KL_NOTIFY);

				switch ($changePage) {
					case self::FWD:
						$currentPage=$subPanelPage[($subPanelPagePos+1)%$subPanelPageLength];
						if ($debug) $this->LogMessage("next Page $currentPage",KL_NOTIFY);
			
						break;
					case self::RWD:
						$subPanelPagePos = ($subPanelPagePos-1)%$subPanelPageLength < 0 ?  $subPanelPageLength-1 : ($subPanelPagePos-1)%$subPanelPageLength;
						$currentPage = $subPanelPage[$subPanelPagePos];
						if ($debug) $this->LogMessage("prev Page $currentPage subPanelPagePos $subPanelPagePos",KL_NOTIFY);
						break;
						}

				}
			$this->WriteAttributeInteger('currentPage',$currentPage);

			# darzustellende Seite aus den Array laden
			$Page = explode('~',$panelPage[$currentPage]['payload'][1]);

			
			$readData = json_decode($this->ReadAttributeString("varAssignment"),true);

			// $readData = array (
			// 	0 =>   array ( 	array ( 'objectId' => 4, 'resultfield' => 8 ), 
			// 					array ( 'split' => '|', 'objectId' => 10, 'resultfield' => 14 ), 
			// 					array ( 'split' => '|', 'objectId' => 16, 'resultfield' => 20 ),
			// 					array ( 'objectId' => 22 , 'resultfield' => 26 ) ,
			// ),
			// 	6252 => array ( array ('objectId' => 1, 'resultfield' => 8 ),
			// ),
			// 	23	=> array (	array ('objectId' => 16, 'resultfield' => 17),
			// 					array ('objectId' => 18, 'resultfield' => 18),
			// 			),

			// );

			# Seite mit Werten füllen
			$registerVariable = array(); # Variablen zum Registrieren via MessageSink			
			if(array_key_exists($currentPage,$readData)){
				foreach ($readData[$currentPage] as $element) {
					$objectId=$element['objectId'];
					if ($debug) $this->LogMessage('page: ' . $currentPage . ', Read: objectId: '.$objectId,KL_NOTIFY);

					if (array_key_exists($element['resultField'],$Page)) { 
						if (IPS_VariableExists($objectId) || IPS_LinkExists($objectId)) {

							$objectType = (IPS_GetObject($objectId))['ObjectType'];

							if ($objectType == 6) {
								$objectId = (IPS_GetLink($objectId))['TargetID'];
							}

							# GetValueFormatted
							if (array_key_exists('formatted',$element) && $element['formatted']) {
								$objectValue=GetValueFormatted($objectId);
							} else {
								$objectValue=GetValue($objectId);
							}
								
							$registerVariable[$objectId]=0; // Var für MessageSink vormerken

							# Wert auf 0 setzen, wenn Var vom Typ bool
							if (is_bool($objectValue)) {
								$objectValue= ($objectValue) ? 1:0;
							}

							if ($debug) $this->LogMessage("getValue: ".$objectValue,KL_NOTIFY);
							if (array_key_exists('split',$element)) {
								$value_array=explode($element['split'],$Page[$element['resultField']]);
								if ($debug) $this->LogMessage("change from: ".$Page[$element['resultField']],KL_NOTIFY);
								$value_array[0]=$objectValue;
								$Page[$element['resultField']]=implode($element['split'],$value_array);
								if ($debug) $this->LogMessage("         to: ".$Page[$element['resultField']],KL_NOTIFY);
							} else {
								$Page[$element['resultField']] = $objectValue;
							}
						} else {
							$this->LogMessage("variable/ link $objectId does not exist",KL_ERROR);
						}
					} else {
						$this->LogMessage('page: ' . $currentPage . ', column ' . $element['resultField'] . ' doesnt exist',KL_ERROR);
					}

				}
			} else {
				$this->LogMessage("nothing defined for page $currentPage in readData",KL_NOTIFY);
			}	
			$this->WriteAttributeString('registerVariable',json_encode($registerVariable));
			# call default-page deaktivieren, wenn popupNotify aufgerufen wird
			if (preg_match('/~popupNotify/',$panelPage[$currentPage]['payload'][0])) {
				if ($debug) $this->LogMessage('disable callCustomScrPage',KL_NOTIFY);
				$this->SetTimerInterval('callCustomScrPage',0);
			}
			return array($panelPage[$currentPage]['payload'][0],implode('~',$Page));
		}


		private function doPanelAction (array $result) {
			$debug = $this->ReadPropertyBoolean("PropertyVariableDebug"); 
			$panelAction = json_decode($this->ReadAttributeString('actionAssignment'),true);
			$panelPage = json_decode($this->ReadAttributeString("panelPage"),true);
			$restoreScreensaver=true;
			if ($debug) $this->LogMessage('-> page/object: ' . $result[1] . ', result: ' . $result[2] . ', filter:' . $result[3] . '',KL_NOTIFY);

			# $panelPage beinhaltet die darzustellenden Seiten, $panelAction die Aktionen, die über die Seiten gestartet werden
			# in einer $panelAction zu einer Seite kann auch der Aufruf einer anderen Seite stehen, somit wird hier geprüft, ob
			# ein Seitenaufruf für eine Seite in $panelPage auch in $panelAction  über $result[2] oder $result[2]:$result[3] 
			# ( gefilterter Aufrufe, bspw popupNotify: vent,buttonPress2,109,notifyAction,yes)

			# Prüfe ob Seite aufgerufen werden soll
			if (array_key_exists($result[1],$panelPage) && !(array_key_exists($result[1],$panelAction) && (array_key_exists($result[2],$panelAction[$result[1]]) || array_key_exists($result[2].':'.$result[3],$panelAction[$result[1]]) ))) { // Aufruf ($result[1]) exists in $panelPage and not in $panelAction-> call page
				if ($debug) $this->LogMessage('call page -> ' . $result[1],KL_NOTIFY);
				$this->sendMqtt_CustomSend($this->Value2Page(self::GO,intval($result[1])));
				$this->registerVariableToMessageSink(true);
			} else {  //  $result[1] exists in $panelAction -> call Action
				if (array_key_exists($result[1],$panelAction) ) {
					if ($debug) $this->LogMessage($result[1].' defined in $panelAction', KL_NOTIFY);
					if (array_key_exists($result[2],$panelAction[$result[1]]) || array_key_exists($result[2].':'.$result[3],$panelAction[$result[1]])) {
						if ($debug) $this->LogMessage('found '.$result[2],KL_NOTIFY);
						
						if (array_key_exists($result[2].':'.$result[3],$panelAction[$result[1]])) {
							# gefilterte $panelAction gefunden, aus $result[2]:$result[3]
							if ($debug) $this->LogMessage('found filter '.$result[3],KL_NOTIFY);
							$doAction = $result[2].':'.$result[3];
						} else {
							$doAction = $result[2];
						}

						# action abarbeiten: 0: RequestAction, 1: RunScript, 2: Goto Page, 3: RunscriptEx 
						if ($panelAction[$result[1]][$doAction]['action'] == 0 ) {
							if (IPS_VariableExists($panelAction[$result[1]][$doAction]['id'])) {
								if ($debug) $this->LogMessage('RequestAction for object '.$panelAction[$result[1]][$doAction]['id'], KL_NOTIFY);
								if (array_key_exists('toggle',$panelAction[$result[1]][$doAction]) && $panelAction[$result[1]][$doAction]['toggle']) {
									if ($debug) $this->LogMessage('toggle', KL_NOTIFY);
									$objectState = GetValue($panelAction[$result[1]][$doAction]['id']);
									if ($objectState) {
										if ($debug) $this->LogMessage(' --> off',KL_NOTIFY);
										RequestAction($panelAction[$result[1]][$doAction]['id'],0);
									}else {
										RequestAction($panelAction[$result[1]][$doAction]['id'],1);
										if ($debug) $this->LogMessage(' --> on',KL_NOTIFY);
									}
								} elseif (array_key_exists('value',$panelAction[$result[1]][$doAction])) {
									if ($debug) $this->LogMessage('predefined value',KL_NOTIFY);
									RequestAction($panelAction[$result[1]][$doAction]['id'],$panelAction[$result[1]][$doAction]['value']);
								} else {
									if ($debug) $this->LogMessage('value: '.$result[3], KL_NOTIFY);
									if (array_key_exists('maxstep',$panelAction[$result[1]][$doAction])) {
										$oldValue = GetValue($panelAction[$result[1]][$doAction]['id']);
										if (($result[3] - $oldValue) > $panelAction[$result[1]][$doAction]['maxstep']) {
											if ($debug) $this->LogMessage("maxstep defined: old $oldValue, new $result[3]",KL_NOTIFY);
											RequestAction($panelAction[$result[1]][$doAction]['id'],$oldValue+$panelAction[$result[1]][$doAction]['maxstep']);
										} else {
											RequestAction($panelAction[$result[1]][$doAction]['id'],$result[3]);
										}
									} else {
										RequestAction($panelAction[$result[1]][$doAction]['id'],$result[3]);
									}
								}
							} else {
								$this->LogMessage('could not found object '.$result[1],KL_ERROR);
							}
						} elseif ($panelAction[$result[1]][$doAction]['action'] == 1 ) {
							if (IPS_ScriptExists($panelAction[$result[1]][$doAction]['id'])) {
								if ($debug) $this->LogMessage('RunScript '.$panelAction[$result[1]][$doAction]['id'],KL_NOTIFY);
								IPS_RunScript($panelAction[$result[1]][$doAction]['id']);
							} else {
								$this->LogMessage('script '.$panelAction[$result[1]][$doAction]['id'].' doesnt exist',KL_ERROR);
							}

						} elseif ($panelAction[$result[1]][$doAction]['action'] == 3 ) {
							if (IPS_ScriptExists($panelAction[$result[1]][$doAction]['id'])) {
								if ($debug) $this->LogMessage('RunScriptEx '.$panelAction[$result[1]][$doAction]['id'],KL_NOTIFY);
								if (array_key_exists('value',$panelAction[$result[1]][$doAction])) {
									if ($debug) $this->LogMessage('predefined value',KL_NOTIFY);
									IPS_RunScriptEx($panelAction[$result[1]][$doAction]['id'],array('value' => $panelAction[$result[1]][$doAction]['value']));
									#RequestAction($panelAction[$result[1]][$doAction]['id'],$panelAction[$result[1]][$doAction]['value']);
								} else {
									if ($debug) $this->LogMessage('value: '.$result[3], KL_NOTIFY);
									IPS_RunScriptEx($panelAction[$result[1]][$doAction]['id'],array('value' => $result[3]));
								}
							} else {
								$this->LogMessage('script '.$panelAction[$result[1]][$doAction]['id'].'F doesnt exist',KL_ERROR);
							}
						} elseif ($panelAction[$result[1]][$doAction]['action'] == 2 ) {
							if ($debug) $this->LogMessage('goto page '.$panelAction[$result[1]][$doAction]['id'],KL_NOTIFY);
							$this->sendMqtt_CustomSend($this->Value2Page(self::GO,intval($panelAction[$result[1]][$doAction]['id'])));
							$this->registerVariableToMessageSink(true);
							$restoreScreensaver=false;
						}
					} elseif (!is_int($result[1]) && $result[1] == 'screensaver') {
						if ($debug) $this->LogMessage("screensaver: could'nt find result $result[2], filter $result[3],default action page:".$this->ReadAttributeInteger('currentPage'),KL_NOTIFY);
						$this->sendMqtt_CustomSend($this->Value2Page(self::GO,$this->ReadAttributeInteger('currentPage')));
						$this->registerVariableToMessageSink(true); # RegisterMessage für Var der aktuellen Seite durchführen   
						$restoreScreensaver=false;
					}
				} elseif (!is_int($result[1]) && $result[1] == 'screensaver') {
					if ($debug) $this->LogMessage('screensaver: default action, show current page:'.$this->ReadAttributeInteger('currentPage'),KL_NOTIFY);
					$this->sendMqtt_CustomSend($this->Value2Page(self::GO,$this->ReadAttributeInteger('currentPage')));
					$this->registerVariableToMessageSink(true); # RegisterMessage für Var der aktuellen Seite durchführen   
					$restoreScreensaver=false;
				} elseif (is_int($result[1]) && $result[1]< 60001 && IPS_VariableExists($result[1]) ) {
					if (array_key_exists(3,$result) && $result[3] != '') {
						if ($debug) $this->LogMessage('default: requestAction for $result[1] with value $result[2]',KL_NOTIFY);
						# durch requestAction wird message-sink ausgelöst, führt zum flackern des Display, Attribut skipMessageSinkforObject wird in MessageSink wiedezurückgesetzt
						$this->WriteAttributeInteger('skipMessageSinkforObject',$result[1]);
						RequestAction($result[1],$result[3]);
					} else {
						$this->LogMessage("no value available for requestAction",KL_ERROR);
					}
				} else {
					$this->LogMessage('RequestAction## for ' . $result[1] . ' not available',KL_NOTIFY);
			
				}
			} #
			if ($restoreScreensaver && $result[1] == 'screensaver') $this->sendMqtt_CustomSend(array($this->ReadPropertyString('sc')));
		}



		public function ReceiveData($JSONString)
		{
			if ($this->ReadAttributeBoolean("Activated")) {
				$data = json_decode($JSONString, true);

				switch ($data['Topic']) {
					case 'tele/' . $this->ReadPropertyString('topic') . '/RESULT':

						# Index der Hauptseiten aufbauen, Anzahl der Hauptseiten ermitteln 
						$payload = json_decode($data['Payload'],true);
						if (array_key_exists('CustomRecv',$payload)) {
							if ($this->ReadPropertyBoolean("PropertyVariableDebug")) $this->LogMessage('receive: -'.$payload['CustomRecv'].'-',KL_NOTIFY);
						} else {
							$this->LogMessage('got strange result: -'.$data['Payload'].'-',KL_NOTIFY);
							return;
						}
						# event ?
						$gotResult='';
						if (preg_match('/event,(.*)/', $payload['CustomRecv'],$matches)) {
							$gotResult=$matches[1];
						} else {
							break;
						}

						# startup
						if (preg_match('/startup,\d+,(eu|us-p|us-l)/', $gotResult)) {
							# set options
							$this->SendBacklog();
							# config screensaver
							if (!empty($this->ReadPropertyString('sc_dimMode'))) {
								$this->sendMqtt_CustomSend(array($this->ReadPropertyString('sc_dimMode')));
							}
							if (!empty($this->ReadPropertyString('sc_timeout'))) {
								$this->sendMqtt_CustomSend(array($this->ReadPropertyString('sc_timeout')));
							}

							$this->sendMqtt_CustomSend($this->Value2Page(self::MAIN,-1));
							$this->registerVariableToMessageSink(true);
						} elseif (preg_match('/sleepReached,card(Entities|Grid|Thermo|Media)/', $gotResult)) { #sleepReached
							if ($this->ReadPropertyBoolean('sc_active') && strlen(trim($this->ReadPropertyString('sc'))) > 0 ) {   
								$this->sendMqtt_CustomSend(array($this->ReadPropertyString('sc')));
								$this->WriteAttributeBoolean('sc_state_active',1);
								$this->LogMessage('state sc: active',KL_NOTIFY);
								$this->RefreshDate(true);
								if ($this->ReadPropertyBoolean('sc_notifyActive') && ($this->ReadPropertyInteger('sc_notifyHeading')>1 || $this->ReadPropertyInteger('sc_notifyText')>1)) {
									$this->sendMqtt_CustomSend(array($this->generateNotifyString()));
								}
								$this->registerVariableToMessageSink(false); # RegisterMessage für Var der aktuellen Seite aufheben   
							}
	
						} elseif (preg_match('/buttonPress2,(.*)/', $gotResult, $matches)) { #buttonPress2
							# restart callCustomScrPage timer
							if ($this->ReadPropertyBoolean('defaultPageActive')) {
								$this->SetTimerInterval('callCustomScrPage',$this->ReadPropertyInteger('defaultPageTime')*1000);
							}
							$gotResultBp=$matches[1];
							if (preg_match('/screensaver,bExit,(\d+)/',$gotResultBp,$matches)) { #screensaver
								if ($this->ReadPropertyBoolean('sc_acceptMultiClicks') && !$this->ReadAttributeBoolean('ScrSaverDoubleClickActive')) {
									$this->WriteAttributeBoolean('ScrSaverDoubleClickActive',1);
									$this->WriteAttributeString('ScrSaverResult',$matches[1]);
									$this->SetTimerInterval('ScrSaverDoubleClick',$this->ReadPropertyInteger('sc_multiClickWaitTime'));
									return;
								} 

								if ($this->ReadAttributeBoolean('ScrSaverDoubleClickActive') ) {
									$this->WriteAttributeString('ScrSaverResult',$matches[1]);
									return;
								}
								$this->RefreshDate(false);
								$this->sendMqtt_CustomSend($this->Value2Page(self::GO,$this->ReadAttributeInteger('currentPage')));
								$this->registerVariableToMessageSink(true); # RegisterMessage für Var der aktuellen Seite durchführen   
								$this->WriteAttributeBoolean('sc_state_active',0);
							} elseif (preg_match('/screensaver,(swipe(Left|Right|Up|Down))/',$gotResultBp,$matches)) { #screensaver swipe
								if ($this->ReadPropertyBoolean('sc_acceptSwipes')) {
									$this->RefreshDate(false);
									$this->sendMqtt_CustomSend($this->Value2Page(self::GO,$this->ReadAttributeInteger('currentPage')));
									$this->registerVariableToMessageSink(true); # RegisterMessage für Var der aktuellen Seite durchführen   
									$this->WriteAttributeBoolean('sc_state_active',0);
								} else {
									$this->doPanelAction(array('','screensaver',$matches[1],''));
								}
							} elseif (preg_match('/popup.*,bExit/', $gotResult)) { # popup.*,bExit
								$this->sendMqtt_CustomSend($this->Value2Page(self::UP,$this->ReadAttributeInteger('currentPage')));
								$this->registerVariableToMessageSink(true);
							} elseif (preg_match('/card.*,bPrev/', $gotResult)) { # card.*,bPrev
								$this->sendMqtt_CustomSend($this->Value2Page(self::RWD,0-1));
								$this->registerVariableToMessageSink(true);
							} elseif (preg_match('/card.*,bNext/', $gotResult)) { # card.*,bNext
								$this->sendMqtt_CustomSend($this->Value2Page(self::FWD,0-1));
								$this->registerVariableToMessageSink(true);
							} elseif (preg_match('/card.*,bUp/', $gotResult)) { # card.*,bUp
								$this->sendMqtt_CustomSend($this->Value2Page(self::UP,-1));
								$this->registerVariableToMessageSink(true);
							} else {
								$debug = $this->ReadPropertyBoolean("PropertyVariableDebug"); 
	
								# 0 RequestAction 1 Script 2 Page
								# Yamha-MusicCast Play 1, stop 3, pause 2 next 4, prev 0
								# Shelly up 0, down 4, stop 2
								# Fibaro up 4, close 0, stop 2
							
								if (preg_match('/event,buttonPress2,(\d*),([^,]*),*([(yes|no)\d]*)/', $payload['CustomRecv'],$result) ) {
									$this->LogMessage('R:'.implode('--',$result),KL_NOTIFY);
#									$this->doPanelAction($result);
									$this->doPanelAction(array('',intval($result[1]),$result[2],$result[3]));
								}
							}
						} 

					case 'stat/' . $this->ReadPropertyString('topic') . '/RESULT': 	
						$payload = json_decode($data['Payload'],true);
						if (array_key_exists('POWER1',$payload)) {
							if ($payload['POWER1'] == 'ON') {
								$this->SetValue('Power1',true);
							} else {
								$this->SetValue('Power1',false);
							}
						}
						elseif (array_key_exists('POWER2',$payload)) {
							if ($payload['POWER2'] == 'ON') {
								$this->SetValue('Power2',true);
							} else {
								$this->SetValue('Power2',false);
							}
						} elseif (array_key_exists('Button1',$payload)) {
							if (array_key_exists('Action',$payload['Button1'])) {
								$this->doPanelAction(array('','Button1','Action',$payload['Button1']['Action']));
							}
						} elseif (array_key_exists('Button2',$payload)) {
							if (array_key_exists('Action',$payload['Button2'])) {
								$this->doPanelAction(array('','Button2','Action',$payload['Button2']['Action']));
							}
						}
						#$this->LogMessage('stat/RESULT/'.$data['Payload'],KL_NOTIFY);
						break;
				}				
			}
		}


		public function GetConfigurationForm() {

			$Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
			//return json_encode($Form);			
			$panelPage = json_decode($this->ReadAttributeString("panelPage"),true);
			
			# Bereich elements

			reset($panelPage);

			foreach ($Form['elements'] as $keyLayer0 => $elementLayer0) {

				# Screensaver 
				foreach ($Form['elements'][$keyLayer0] as $keyLayer1 => $elementLayer1) {
					if ($elementLayer1 === 'ExpansionPanel') {
						foreach ($Form['elements'][$keyLayer0]['items'] as $keyLayer2 => $elementLayer2) {
							foreach ($Form['elements'][$keyLayer0]['items'][$keyLayer2] as $keyLayer3 => $elementLayer3) {
								if ($elementLayer3 === 'RowLayout') {
									foreach($Form['elements'][$keyLayer0]['items'][$keyLayer2]['items'] as $keyLayer4 => $elementLayer4) {
										if ($Form['elements'][$keyLayer0]['items'][$keyLayer2]['items'][$keyLayer4]['name'] === 'sc') {
											if (!$this->ReadPropertyBoolean('sc_active')) $Form['elements'][$keyLayer0]['items'][$keyLayer2]['items'][$keyLayer4]['enabled'] = false;
										}
										if ($Form['elements'][$keyLayer0]['items'][$keyLayer2]['items'][$keyLayer4]['name'] === 'sc_multiClickWaitTime') {
											if (!$this->ReadPropertyBoolean('sc_acceptMultiClicks')) $Form['elements'][$keyLayer0]['items'][$keyLayer2]['items'][$keyLayer4]['enabled'] = false;
										}
										if ($Form['elements'][$keyLayer0]['items'][$keyLayer2]['items'][$keyLayer4]['name'] === 'sc_notifyHeading') {
											if (!$this->ReadPropertyBoolean('sc_notifyActive')) $Form['elements'][$keyLayer0]['items'][$keyLayer2]['items'][$keyLayer4]['enabled'] = false;
										}
										if ($Form['elements'][$keyLayer0]['items'][$keyLayer2]['items'][$keyLayer4]['name'] === 'sc_notifyText') {
											if (!$this->ReadPropertyBoolean('sc_notifyActive')) $Form['elements'][$keyLayer0]['items'][$keyLayer2]['items'][$keyLayer4]['enabled'] = false;
										}
										if ($Form['elements'][$keyLayer0]['items'][$keyLayer2]['items'][$keyLayer4]['name'] === 'defaultPageTime') {
											if (!$this->ReadPropertyBoolean('defaultPageActive')) $Form['elements'][$keyLayer0]['items'][$keyLayer2]['items'][$keyLayer4]['enabled'] = false;
										}
										if ($Form['elements'][$keyLayer0]['items'][$keyLayer2]['items'][$keyLayer4]['name'] === 'defaultPage') {
											if (!$this->ReadPropertyBoolean('defaultPageActive')) $Form['elements'][$keyLayer0]['items'][$keyLayer2]['items'][$keyLayer4]['enabled'] = false;
											$cnt=0;
											$Form['elements'][$keyLayer0]['items'][$keyLayer2]['items'][$keyLayer4]['options'][$cnt]['caption'] = '-';
											$Form['elements'][$keyLayer0]['items'][$keyLayer2]['items'][$keyLayer4]['options'][$cnt]['value'] = -1;
											$cnt++;
											foreach ($panelPage as $pageKey => $pageElement) {
												$Form['elements'][$keyLayer0]['items'][$keyLayer2]['items'][$keyLayer4]['options'][$cnt]['caption'] = $pageKey;
												$Form['elements'][$keyLayer0]['items'][$keyLayer2]['items'][$keyLayer4]['options'][$cnt]['value'] = $pageKey;
												$cnt++;
											}
										}
									}
								}
							}
						}
					}

					# Seitendefinition

					if ($elementLayer1 === 'RowLayout') {
						foreach ($Form['elements'][$keyLayer0]['items'] as $keyLayer2 => $elementLayer2) {
							foreach ($Form['elements'][$keyLayer0]['items'][$keyLayer2] as $keyLayer3 => $elementLayer3) {
								if ($elementLayer3 === 'panelPageValuesArray') {
									foreach($elementLayer2['columns'] as $keyColumn => $columnsElement) {
										if ($columnsElement['name'] == 'panelPage') { # Suche nach panelPage
											$cnt=0;
											$addSet=true;
											foreach ($panelPage as $pageKey => $pageElement) {
												if ($addSet) {
													$Form['elements'][$keyLayer0]['items'][$keyLayer2]['columns'][$keyColumn]['add'] = "$pageKey";
													$addSet=false;
												}
												$Form['elements'][$keyLayer0]['items'][$keyLayer2]['columns'][$keyColumn]['edit']['options'][$cnt]['caption'] = "$pageKey";
												$Form['elements'][$keyLayer0]['items'][$keyLayer2]['columns'][$keyColumn]['edit']['options'][$cnt]['value'] = "$pageKey";
												$cnt++;
											}
										}
										# Wertzuweisung
										elseif ($columnsElement['name'] == 'panelPageValues') {
											foreach ($columnsElement['edit']['columns'] as $selectKey => $selectElement){
												if ($selectElement['name'] == 'resultField') { # Suche nach resultField
													$cnt=0;
													reset($panelPage);
													if (count($panelPage) > 0) {
														$firstPageKey=key($panelPage);
														foreach (explode('~',$panelPage[$firstPageKey]['payload'][1]) as $pageEntryColum => $pageEntryElement) {
															$Form['elements'][$keyLayer0]['items'][$keyLayer2]['columns'][$keyColumn]['edit']['columns'][$selectKey]['edit']['options'][$cnt]['caption'] = "($pageEntryColum) $pageEntryElement";
															$Form['elements'][$keyLayer0]['items'][$keyLayer2]['columns'][$keyColumn]['edit']['columns'][$selectKey]['edit']['options'][$cnt]['value'] = "$pageEntryColum";
															$cnt++;
														}
													}
												} elseif ($selectElement['name'] == 'objectId') { # Suche nach objectId
													$cnt=0;
													reset($panelPage);
													if (count($panelPage) > 0) {
														$firstPageKey=key($panelPage);
														foreach (explode('~',$panelPage[$firstPageKey]['payload'][1]) as $pageEntryColum => $pageEntryElement) {
#															$Form['elements'][$key]['columns'][$keyColumn]['edit']['columns'][$selectKey]['edit']['options'][$cnt]['caption'] = "($pageEntryColum) $pageEntryElement";
#															$Form['elements'][$key]['columns'][$keyColumn]['edit']['columns'][$selectKey]['edit']['options'][$cnt]['value'] = "$pageEntryColum";
															$Form['elements'][$keyLayer0]['items'][$keyLayer2]['columns'][$keyColumn]['edit']['columns'][$selectKey]['edit']['options'][$cnt]['caption'] = "($pageEntryColum) $pageEntryElement";
															$Form['elements'][$keyLayer0]['items'][$keyLayer2]['columns'][$keyColumn]['edit']['columns'][$selectKey]['edit']['options'][$cnt]['value'] = "$pageEntryColum";
															$cnt++;
														}
													}
												}
											}
										}
									}
								}
							}
						}
					}
				}
			}

			# Bereich actions
			# DropDown-Liste der Seitennummern erstellen
			foreach ($Form['actions'] as $key => $element) {
				if (array_key_exists('items',$element)) {
					foreach ($element['items'] as $keyItem => $itemElement ) {
						# Seitenauswahl
						if (array_key_exists('name',$itemElement) && $itemElement['name'] == 'showPage') {
							$cnt=0;
							foreach ($panelPage as $pageKey => $pageElement) {
								$Form['actions'][$key]['items'][$keyItem]['options'][$cnt]['caption'] = $pageKey;
								$Form['actions'][$key]['items'][$keyItem]['options'][$cnt]['value'] = $pageKey;
								$cnt++;
							}
							reset($panelPage);

						# Load configuration
						} elseif (array_key_exists('name',$itemElement) && $itemElement['name'] == 'showPageColumns') {	
							$cnt=0;
							reset($panelPage);
							if (count($panelPage)> 0 && array_key_exists('payload',$panelPage[key($panelPage)]) && array_key_exists('1',$panelPage[key($panelPage)]['payload'])) {
								foreach (explode('~',$panelPage[key($panelPage)]['payload'][1]) as $element) {
									$Form['actions'][$key]['items'][$keyItem]['columns'][$cnt]['caption']="$cnt";
									$Form['actions'][$key]['items'][$keyItem]['columns'][$cnt]['name']="id$cnt";
									$Form['actions'][$key]['items'][$keyItem]['columns'][$cnt]['width']=(strlen($element)*12+5).'px';
									$Form['actions'][$key]['items'][$keyItem]['values'][0]["id$cnt"]="$element";
									$cnt++;
								}
							}
						} elseif (array_key_exists('name',$itemElement) && $itemElement['name'] == 'loadConfig') {
							$cnt=0;
							foreach (IPS_GetChildrenIDs($this->InstanceID) as $id) {
								$backupname=(IPS_GetObject($id))['ObjectIdent'];
								if (preg_match('/backup(pages|assignment|action)\d+/',(IPS_GetObject($id))['ObjectIdent'])) {
									$Form['actions'][$key]['items'][$keyItem]['options'][$cnt]['caption'] = (IPS_GetObject($id))['ObjectName'];
									$Form['actions'][$key]['items'][$keyItem]['options'][$cnt]['value'] = (IPS_GetObject($id))['ObjectIdent'];
									$cnt++;
								}
							}
						}
					}
				} elseif (array_key_exists('name',$element) && $element['name'] == 'activated') {
					$Form['actions'][$key]['value']=$this->ReadAttributeBoolean("Activated");
				}
			}

			

			return json_encode($Form);

		}



		// wird vom Formular aufgerufen : "onChange":"DBNSP_SwapModuleStatus($id);"
		private function SwapModuleStatus () {
			$mystate = $this->GetStatus();
			if ($mystate == 104 ) {
				$this->SetModuleActive(true);
				}
			elseif ($mystate == 102 ) {
				$this->SetModuleActive(false);
				}
			}

		private function SetModuleActive(bool $state) {
			//102	Instanz ist aktiv
			//104	Instanz ist inaktiv
			if ($state) {
				$this->SetStatus(102);
				}
			else {
			  	$this->SetStatus(104);
				}
			$mystate = $this->GetStatus();
			if ($mystate == 102 ) {
				$this->LogMessage("enabled",KL_NOTIFY);
				$this->WriteAttributeBoolean("Activated",true);
				$this->UpdateFormField("activated","value",true);
#				$this->SetTimerInterval("Refresh",5000);
				}
			else {
				$this->LogMessage("disabled",KL_NOTIFY);
				$this->WriteAttributeBoolean("Activated",false);
				$this->UpdateFormField("activated","value",false);
				$this->SetTimerInterval("Refresh",0);
				}
			}


	}