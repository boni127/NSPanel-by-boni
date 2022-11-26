<?php

# Todo Beim Laden des Konfigurators wird der reste Eintrag nich tin den ListHelper geladen
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


			$this->RegisterAttributeInteger('currentPage',0);   # aktuell dargestellte seite
//			$this->RegisterAttributeInteger('lastPage',-1);     # die zuvor dargestellte Seite
			$this->RegisterAttributeString('panelPage','{}');   # Seitendefinition interne Nutzung
			$this->RegisterAttributeString('varAssignment','{}'); # Wertzuweisung der einzelnen Seiten, interne Nutzung
			$this->RegisterAttributeString('registerVariable',''); # Variablen überwachen (MessageSink)
			$this->RegisterAttributeInteger('skipMessageSinkforObject',0); # Objekt-ID für die Message-Sink übersrungen wird, wenn Objekt-Änderung durch Interaktion am Display erfogt
			$this->RegisterPropertyString('panelPageConf','{}');   # Seitendefinition im Formular
			$this->RegisterPropertyString('panelPageValuesArray','{}'); # Wertzuweisung der einzelnen Seiten

			// Status der Instanz speichern
			$this->RegisterAttributeBoolean("Activated",false);


			$this->RegisterTimer("Refresh",10, 'DBNSP_RefreshDate('.$this->InstanceID . ');');
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

//			$this->LogMessage('1:'.$varAssignmentSrc[0]['panelPage'],KL_NOTIFY);

			foreach ($varAssignmentSrc as $listEntry) {
				$this->LogMessage(implode(':',array_keys($listEntry)),KL_NOTIFY);
				$this->LogMessage('Seite :'.$listEntry['panelPage'],KL_NOTIFY);
				$this->LogMessage(implode(':',array_keys($listEntry['panelPageValues'])),KL_NOTIFY);
				$cnt=0;
				foreach ($listEntry['panelPageValues'] as $pageEntry) {
					if (array_key_exists('objectId', $pageEntry)) $varAssignmentDst[$listEntry['panelPage']][$cnt]['objectId'] = $pageEntry['objectId'];
					if (array_key_exists('split', $pageEntry) && strlen(trim($pageEntry['split']))>0) $varAssignmentDst[$listEntry['panelPage']][$cnt]['split'] = $pageEntry['split'];
					if (array_key_exists('resultField', $pageEntry)) $varAssignmentDst[$listEntry['panelPage']][$cnt]['resultField'] = $pageEntry['resultField'];
					$cnt++;
					$this->LogMessage(' -'.$pageEntry['objectId'] ,KL_NOTIFY);
				 }
			}
			$this->LogMessage('---ApplyChange',KL_NOTIFY);
			$this->LogMessage('---'.json_encode($varAssignmentDst),KL_NOTIFY);

			// Werte speichern
			$this->WriteAttributeString('panelPage',json_encode($panelPageDst));
			$this->WriteAttributeString('varAssignment', json_encode($varAssignmentDst));

			$this->sendMqtt_CustomSend(array('pageType~pageStartup'));	

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
					$this->LogMessage('skip message for '.$this->ReadAttributeInteger('skipMessageSinkforObject') ,KL_NOTIFY);
					$this->WriteAttributeInteger('skipMessageSinkforObject',0);
				} else {
					$this->LogMessage('MessageSink: sender ' . $SenderID . ' Message ' . $Message,KL_NOTIFY);
					$this->sendMqtt_CustomSend($this->Value2Page(self::GO,$this->ReadAttributeInteger('currentPage')));
				}
			}
		}

		private function registerVariableToMessageSink (bool $register ) {
			if ($register) {
				foreach(json_decode($this->ReadAttributeString('registerVariable'),true) as $element){
					$this->RegisterMessage($element, VM_UPDATE);
					if ($this->ReadPropertyBoolean("PropertyVariableDebug")) $this->LogMessage('var to observe: '.$element,KL_NOTIFY);
				}
			} else {
				foreach(json_decode($this->ReadAttributeString('registerVariable'),true) as $element){
					$this->UnregisterMessage($element, VM_UPDATE);
					if ($this->ReadPropertyBoolean("PropertyVariableDebug")) $this->LogMessage('remove from observe: '.$element,KL_NOTIFY);
				}
			$this->WriteAttributeString('registerVariable',json_encode(array()));
			}
		}

		public function RefreshDate () {
			$id=$this->InstanceID;
			$this->SetTimerInterval("Refresh",(60-date("s",time()))*1000);
			$now_time = date("H:i", time());
			$now_date = strftime("%A %d. %b %Y", time());
			$this->sendMqtt_CustomSend(array("time~$now_time","date~$now_date"));
		}

		public function sendTime(){
			$data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
			$data['PacketType'] = 3;
            $data['QualityOfService'] = 0;
            $data['Retain'] = false;
            $data['Topic'] = 'cmnd/'.$this->ReadPropertyString('topic').'/CustomSend';
            $data['Payload'] = $Text;
			$this->SendDataToParent(json_encode($data, JSON_UNESCAPED_SLASHES));
			$this->LogMessage('mod_nspanel:'.json_encode($data, JSON_UNESCAPED_SLASHES),KL_NOTIFY);			
		}

		public function Send(string $Text)
		{
		
			$data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
			$data['PacketType'] = 3;
            $data['QualityOfService'] = 0;
            $data['Retain'] = false;
            $data['Topic'] = 'cmnd/'.$this->ReadPropertyString('topic').'/CustomSend';
            $data['Payload'] = $Text;
			$this->SendDataToParent(json_encode($data, JSON_UNESCAPED_SLASHES));
			$this->LogMessage('mod_nspanel:'.json_encode($data, JSON_UNESCAPED_SLASHES),KL_NOTIFY);
		}

		public function LoadEntry(string $page) {
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
				$this->LogMessage('Select: '.json_encode($entry),KL_NOTIFY);
				$this->UpdateFormField("showPageColumns", "columns", json_encode($entry));
				$this->UpdateFormField("showPageColumns", "values", json_encode($values));
			}
		}

		public function Save() {
			$name=array();
			$save_name='backup';
			foreach (IPS_GetChildrenIDs($this->InstanceID) as $id) {
				$name[(IPS_GetObject($id))['ObjectIdent']]=$id;
			}
			$this->LogMessage('Keys: '.implode('-',array_keys($name)),KL_NOTIFY);
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

			$this->UpdateFormField("save","enabled",false);
		}

		public function Load(string $backupname) {
			$this->LogMessage('Load: '.$backupname,KL_NOTIFY);
			if (preg_match('/backuppages\d+/',$backupname) ) {
				$this->UpdateFormField('panelPageConf','values',json_decode($this->GetValue($backupname)));
			} elseif (preg_match('/backupassignment\d+/',$backupname) ) {
				$this->UpdateFormField('panelPageValuesArray','values',json_decode($this->GetValue($backupname)));
			}

		}


		function LoadPageColumns(string $panelKey) {
			$this->LogMessage("load Values into configurator for Page $panelKey",KL_NOTIFY);
			$panelPage = json_decode($this->ReadAttributeString("panelPage"),true);
			$columnNumber=0;
			$entry=array();
			foreach (explode('~',$panelPage[$panelKey]['payload'][1]) as $pageEntryColum => $pageEntryElement) {
				$entry[$columnNumber]['caption'] = "($pageEntryColum) $pageEntryElement";
				$entry[$columnNumber]['value'] = "$pageEntryColum";
				$columnNumber++;
			}
			$this->LogMessage("load Entry ".json_encode($entry),KL_NOTIFY);
			$this->UpdateFormField("resultField", "options", json_encode($entry));
			$this->UpdateFormField("objectId", "options", json_encode($entry));
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

		public function Value2Page(int $changePage,int $showPage) 
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
				if (array_key_exists('return',$panelPage[$currentPage])){
					$currentPage = $panelPage[$currentPage]['return'];
				} elseif (array_key_exists('main',$panelPage[$currentPage])){
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
						$this->LogMessage("Value2Page (v) next Page $currentPage",KL_NOTIFY);
			
						break;
					case self::RWD:
						$subPanelPagePos = ($subPanelPagePos-1)%$subPanelPageLength < 0 ?  $subPanelPageLength-1 : ($subPanelPagePos-1)%$subPanelPageLength;
						$currentPage = $subPanelPage[$subPanelPagePos];
						$this->LogMessage("Value2Page (r) prev Page $currentPage subPanelPagePos $subPanelPagePos",KL_NOTIFY);
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
					if (array_key_exists($element['objectId'],$Page)) {
						$objectId = $Page[$element['objectId']];
						if ($debug) $this->LogMessage("readData for Page $currentPage object (".$objectId.") from field:".$element['objectId'].' result to field:'.$element['resultField'],KL_NOTIFY);
					} else {
						$this->LogMessage("readData Page $currentPage, couldn't find  column ".$element['objectId'],KL_NOTIFY);
						continue;
					}

					if (IPS_VariableExists($objectId) ) {
						# Wert auslesen
						$objectValue=GetValue($objectId);

						$registerVariable[]=$objectId; // Var für MessageSink vormerken

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
						$this->LogMessage("variable $objectId does not exist",KL_NOTIFY);
					}

				}
			} else {
				$this->LogMessage("nothing defined for key $currentPage in readData",KL_NOTIFY);
			}	
			$this->WriteAttributeString('registerVariable',json_encode($registerVariable));
			return array($panelPage[$currentPage]['payload'][0],implode('~',$Page));
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
							$this->LogMessage('receive: -'.$payload['CustomRecv'].'-',KL_NOTIFY);
						} else {
							$this->LogMessage('got strange result: -'.$data['Payload'].'-',KL_NOTIFY);
							return;
						}

						switch ($payload['CustomRecv']) {

							case 'event,startup,43,eu':
								if (!empty($this->ReadPropertyString('sc_dimMode'))) {
									$this->sendMqtt_CustomSend(array($this->ReadPropertyString('sc_dimMode')));
								}
								if (!empty($this->ReadPropertyString('sc_timeout'))) {
									$this->sendMqtt_CustomSend(array($this->ReadPropertyString('sc_timeout')));
								}

								$this->sendMqtt_CustomSend($this->Value2Page(self::MAIN,-1));
								$this->registerVariableToMessageSink(true);
								break;

							case 'event,sleepReached,cardEntities':
							case 'event,sleepReached,cardGrid':
								if ( $this->ReadPropertyBoolean('sc_active') && strlen(trim($this->ReadPropertyString('sc'))) > 0 ) {   
									$now_time = date("H:i", time());
									$now_date = strftime("%A %d. %b %Y", time());
									$this->sendMqtt_CustomSend(array($this->ReadPropertyString('sc'),"time~$now_time","date~$now_date"));
									$this->registerVariableToMessageSink(false);
								}
								break;

							case 'event,buttonPress2,screensaver,bExit,1':
								$this->sendMqtt_CustomSend($this->Value2Page(self::GO,$this->ReadAttributeInteger('currentPage')));
								$this->registerVariableToMessageSink(true);
								break;

							case 'event,buttonPress2,popupLight,bExit';
								$this->sendMqtt_CustomSend($this->Value2Page(self::MAIN,-1));
								break;

							case 'event,buttonPress2,cardEntities,bPrev':
							case 'event,buttonPress2,cardGrid,bPrev':
								$this->sendMqtt_CustomSend($this->Value2Page(self::RWD,0-1));
								break;
		
							case 'event,buttonPress2,cardEntities,bNext':
							case 'event,buttonPress2,cardGrid,bNext':
								$this->sendMqtt_CustomSend($this->Value2Page(self::FWD,-1));
								break;

							case 'event,buttonPress2,popupNotify,bExit':
								$this->sendMqtt_CustomSend($this->Value2Page(self::UP,-1));
								break;
							case 'event,buttonPress2,cardGrid,bUp':
								$this->sendMqtt_CustomSend($this->Value2Page(self::UP,-1));
								break;

							}

						if (preg_match('/event,buttonPress2,(.*),(number-set|OnOff|button),*(\d*)/', $payload['CustomRecv'],$result) ) {
							$this->LogMessage('-> ' . $result[1] . ' - ' . $result[2] . ' - ' . $result[3],KL_NOTIFY);
							# Prüfe ob Seite aufgerufen werden soll
							$panelPage = json_decode($this->ReadAttributeString("panelPage"),true);
							if (array_key_exists($result[1],$panelPage)) {
								$this->LogMessage('Unterseite -> ' . $result[1],KL_NOTIFY);
								$this->sendMqtt_CustomSend($this->Value2Page(self::GO,$result[1]));
							} else {
								if (IPS_VariableExists($result[1]) ) {
									# durch requestAction wird message-sink ausgelöst, führt zum flackern des Display, Attribut skipMessageSinkforObject wird in MessageSink wiedezurückgesetzt
									$this->WriteAttributeInteger('skipMessageSinkforObject',$result[1]);
									RequestAction($result[1],$result[3]);
								} else {
									$this->LogMessage('RequestAction for ' . $result[1] . ' not available',KL_NOTIFY);
							
								}
						
							}
						}
					
					}
				
			}

		}


		public function GetConfigurationForm() {
			$Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
		//	return json_encode($Form);			
			$panelPage = json_decode($this->ReadAttributeString("panelPage"),true);
			
			# Bereich elements

			foreach($panelPage as $key => $element) {
				$this->LogMessage('key: '.$key,KL_NOTIFY);
				foreach($element as $key2 => $element2) {
					$this->LogMessage(' --:'.$key2,KL_NOTIFY);
					if ($key2 == 'payload') {
						$this->LogMessage(' --:'.implode('#',$element2),KL_NOTIFY);
					}
					// foreach($element2 as $key3 => $element3) {
					// 	$this->LogMessage(' --:'.$key3,KL_NOTIFY);
					// }
				}
			}
			reset($panelPage);
//			$a=key($panelPage);

			foreach ($Form['elements'] as $key => $element) {
				$this->LogMessage('1 '.$element['type'],KL_NOTIFY);
				# Seitendefinition
				if (array_key_exists('name',$element) && $element['name'] == 'panelPageValuesArray') { # Suche nach panelPageValuesArray
					foreach($element['columns'] as $keyColumn => $columnsElement) {
						$this->LogMessage('  '."$keyColumn",KL_NOTIFY);
						if ($columnsElement['name'] == 'panelPage') { # Suche nach panelPage
							$cnt=0;
							$addSet=true;
							foreach ($panelPage as $pageKey => $pageElement) {
								if ($addSet) {
									$Form['elements'][$key]['columns'][$keyColumn]['add'] = "$pageKey";
									$addSet=false;
								}
								$this->LogMessage('  + '.$cnt,KL_NOTIFY);
								$Form['elements'][$key]['columns'][$keyColumn]['edit']['options'][$cnt]['caption'] = "$pageKey";
								$Form['elements'][$key]['columns'][$keyColumn]['edit']['options'][$cnt]['value'] = "$pageKey";
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
											$Form['elements'][$key]['columns'][$keyColumn]['edit']['columns'][$selectKey]['edit']['options'][$cnt]['caption'] = "($pageEntryColum) $pageEntryElement";
											$Form['elements'][$key]['columns'][$keyColumn]['edit']['columns'][$selectKey]['edit']['options'][$cnt]['value'] = "$pageEntryColum";
											$cnt++;
										}
									}
								} elseif ($selectElement['name'] == 'objectId') { # Suche nach objectId
									$cnt=0;
									reset($panelPage);
									if (count($panelPage) > 0) {
										$firstPageKey=key($panelPage);
										foreach (explode('~',$panelPage[$firstPageKey]['payload'][1]) as $pageEntryColum => $pageEntryElement) {
											$Form['elements'][$key]['columns'][$keyColumn]['edit']['columns'][$selectKey]['edit']['options'][$cnt]['caption'] = "($pageEntryColum) $pageEntryElement";
											$Form['elements'][$key]['columns'][$keyColumn]['edit']['columns'][$selectKey]['edit']['options'][$cnt]['value'] = "$pageEntryColum";
											$cnt++;
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
				$this->LogMessage($element['type'],KL_NOTIFY);
				if (array_key_exists('items',$element)) {
					foreach ($element['items'] as $keyItem => $itemElement ) {
						# Seitenauswahl
						if (array_key_exists('name',$itemElement) && $itemElement['name'] == 'showPage') {
							$this->LogMessage(' - '.$itemElement['name'],KL_NOTIFY);
							$this->LogMessage(' -  Key '.implode(':',array_keys($itemElement)),KL_NOTIFY);
							$this->LogMessage(' -  Index '."$key : $keyItem",KL_NOTIFY);
							$this->LogMessage(' -  Entry '.$Form['actions'][$key]['items'][$keyItem]['caption'],KL_NOTIFY);
							$cnt=0;
							foreach ($panelPage as $pageKey => $pageElement) {
								$this->LogMessage('---Seitenhilfe'.$pageKey,KL_NOTIFY);
								$Form['actions'][$key]['items'][$keyItem]['options'][$cnt]['caption'] = $pageKey;
								$Form['actions'][$key]['items'][$keyItem]['options'][$cnt]['value'] = $pageKey;
								$cnt++;
							}
							reset($panelPage);
							//$this->LoadEntry(strval(key($panelPage)));
							$this->LogMessage('---Seitenhilfe lade ' .key($panelPage),KL_NOTIFY);

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
							$this->LogMessage(' - '.$itemElement['name'],KL_NOTIFY);
							$cnt=0;
							foreach (IPS_GetChildrenIDs($this->InstanceID) as $id) {
								$backupname=(IPS_GetObject($id))['ObjectIdent'];
								if (preg_match('/backup(pages|assignment)\d+/',(IPS_GetObject($id))['ObjectIdent'])) {
									$this->LogMessage('Save:'.$id.' - '.(IPS_GetObject($id))['ObjectName'],KL_NOTIFY );
									$this->LogMessage("['actions'][$key]['items'][$keyItem]['options'][$cnt]['caption'] ",KL_NOTIFY);
									$Form['actions'][$key]['items'][$keyItem]['options'][$cnt]['caption'] = (IPS_GetObject($id))['ObjectName'];
									$Form['actions'][$key]['items'][$keyItem]['options'][$cnt]['value'] = (IPS_GetObject($id))['ObjectIdent'];
									$cnt++;
								}
							}
						}
					}
				} 
			}

			return json_encode($Form);

		}



		// wird vom Formular aufgerufen : "onChange":"DBNSP_SwapModuleStatus($id);"
		public function SwapModuleStatus () {
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
				}
			else {
				$this->LogMessage("disabled",KL_NOTIFY);
				$this->WriteAttributeBoolean("Activated",false);
				$this->UpdateFormField("activated","value",false);
				}
			}


	}