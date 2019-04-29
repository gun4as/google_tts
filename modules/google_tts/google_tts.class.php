<?php
/**
* Google TTS
* @package project
*/
//
//
class google_tts extends module {
/**
* yandex_tts
*
* Module class constructor
*
* @access private
*/
function google_tts() {
  $this->name="google_tts";
  $this->title="Google TTS";
  $this->module_category="<#LANG_SECTION_APPLICATIONS#>";
  $this->checkInstalled();
}
/**
* saveParams
*
* Saving module parameters
*
* @access public
*/
function saveParams($data=0) {
 $p=array();
 if (IsSet($this->id)) {
  $p["id"]=$this->id;
 }
 if (IsSet($this->view_mode)) {
  $p["view_mode"]=$this->view_mode;
 }
 if (IsSet($this->edit_mode)) {
  $p["edit_mode"]=$this->edit_mode;
 }
 if (IsSet($this->tab)) {
  $p["tab"]=$this->tab;
 }
 return parent::saveParams($p);
}
/**
* getParams
*
* Getting module parameters from query string
*
* @access public
*/
function getParams() {
  global $id;
  global $mode;
  global $view_mode;
  global $edit_mode;
  global $tab;
  if (isset($id)) {
   $this->id=$id;
  }
  if (isset($mode)) {
   $this->mode=$mode;
  }
  if (isset($view_mode)) {
   $this->view_mode=$view_mode;
  }
  if (isset($edit_mode)) {
   $this->edit_mode=$edit_mode;
  }
  if (isset($tab)) {
   $this->tab=$tab;
  }
}
/**
* Run
*
* Description
*
* @access public
*/
function run() {
 global $session;
  $out=array();
  if ($this->action=='admin') {
   $this->admin($out);
  } else {
   $this->usual($out);
  }
  if (IsSet($this->owner->action)) {
   $out['PARENT_ACTION']=$this->owner->action;
  }
  if (IsSet($this->owner->name)) {
   $out['PARENT_NAME']=$this->owner->name;
  }
  $out['VIEW_MODE']=$this->view_mode;
  $out['EDIT_MODE']=$this->edit_mode;
  $out['MODE']=$this->mode;
  $out['ACTION']=$this->action;
  $this->data=$out;
  $p=new parser(DIR_TEMPLATES.$this->name."/".$this->name.".html", $this->data, $this);
  $this->result=$p->result;
}
/**
* BackEnd
*
* Module backend
*
* @access public
*/
function admin(&$out) {
	$this->getConfig();
	$out['ACCESS_KEY'] = $this->config['ACCESS_KEY'];
	$out['LANGUAGECODE'] = $this->config['LANGUAGECODE'];
	$out['VOICE'] = $this->config['VOICE'];
	$out['EMPHASIS'] = $this->config['EMPHASIS'];
	$out['DISABLED'] = $this->config['DISABLED'];
	switch($this->view_mode) {
		case 'update_settings':
			global $access_key;
			$this->config['ACCESS_KEY'] = $access_key;
			global $languageCode;
			$this->config['LANGUAGECODE'] = $languageCode;
			global $voice;
			$this->config['VOICE'] = $voice;
			global $emphasis;
			$this->config['EMPHASIS'] = $emphasis;
			global $disabled;
			$this->config['DISABLED'] = $disabled;
			$this->saveConfig();
			$this->redirect('?view_mode=ok');
			break;
		case 'clear_cache':
			array_map('unlink', glob(ROOT.'cms/cached/voice/*_google.mp3'));
			$this->redirect('?view_mode=ok');
			break;
		case 'add_emphasis':
			global $search_str, $replace_str, $case;
			if(!empty($search_str) && !empty($replace_str)) {
				if($query = SQLSelectOne("SELECT * FROM `google_tts_emphasis` WHERE `search_str` LIKE '".DBSafe($search_str)."'")) {
					$query['search_str'] = $search_str;
					$query['replace_str'] = $replace_str;
					$query['case'] = ($case=='1'?1:0);
					if(SQLUpdate('google_tts_emphasis', $query)) {
						$this->redirect('?view_mode=ok');
					} else {
						$this->redirect('?view_mode=err');
					}
				} else {
					$query = array();
					$query['search_str'] = $search_str;
					$query['replace_str'] = $replace_str;
					$query['case'] = ($case=='1'?1:0);
					if(SQLInsert('google_tts_emphasis', $query)) {
						$this->redirect('?view_mode=ok');
					} else {
						$this->redirect('?view_mode=err');
					}
				}
			} else {
				$this->redirect('?view_mode=err');
			}
			break;
		case 'delete_emphasis':
			global $id;
			if(SQLExec('DELETE FROM `google_tts_emphasis` WHERE `ID` = '.intval($id))) {
				$this->redirect('?view_mode=ok');
			} else {
				$this->redirect('?view_mode=err');
			}
			break;
		case 'emphasis_clear':
			if(SQLExec('TRUNCATE TABLE `google_tts_emphasis`')) {
				$this->redirect('?view_mode=ok');
			} else {
				$this->redirect('?view_mode=err');
			}
			break;
		case 'emphasis_import':
        	if($this->mode == 'update') {
				$error = FALSE;
            	global $file;
            	if(file_exists($file)) {
                	$tmp = LoadFile($file);
                	$lines = mb_split("\n", $tmp);
					foreach($lines as $line) {
						$line = mb_split(':', $line);
						$count = count($line);
						if($count == 2 || $count == 3) {
							$line[0] = str_replace('&#58;', ':', $line[0]);
							$line[1] = str_replace('&#58;', ':', $line[1]);
							$line[2] = ($count == 3?intval($line[2]):0);
							if($query = SQLSelectOne("SELECT * FROM `google_tts_emphasis` WHERE `search_str` LIKE '".DBSafe($line[0])."'")) {
								$query['search_str'] = $line[0];
								$query['replace_str'] = $line[1];
								$query['case'] = ($line[2]==1?1:0);
								SQLUpdate('google_tts_emphasis', $query);
							} else {
								$query = array();
								$query['search_str'] = $line[0];
								$query['replace_str'] = $line[1];
								$query['case'] = ($line[2]==1?1:0);
								SQLInsert('google_tts_emphasis', $query);
							}
						}
					}
					$this->redirect('?view_mode=ok');
				} else {
					$this->redirect('?view_mode=err');
				}
			}
			break;
		case 'emphasis_export':
			if($emphasis = SQLSelect('SELECT * FROM `google_tts_emphasis` ORDER BY `search_str`')) {
				$data = '';
				foreach($emphasis as $item) {
					$item['search_str'] = str_replace(':', '&#58;', $item['search_str']);
					$item['replace_str'] = str_replace(':', '&#58;', $item['replace_str']);
					$item['case'] = intval($item['case']);
					$data .= $item['search_str'].':'.$item['replace_str'].':'.$item['case'].PHP_EOL;
				}
				header('Content-Disposition: attachment; filename=google_tts_export_'.date('d-m-Y_H-i-s').'.dic');
				header('Content-Type: text/plain');
				die($data);
			} else {
				$this->redirect('?view_mode=err');
			}
			break;
		case 'ok':
			$out['OK'] = 1;
			break;
		case 'err':
			$out['ERR'] = 1;
			break;
	}
	// Show emphasis list
	$emphasis = SQLSelect('SELECT * FROM `google_tts_emphasis` ORDER BY `search_str`');
	foreach($emphasis as $item) {
		$out['EMPHASIS_LIST'][] = array(
			'ID'			=> $item['ID'],
			'search_str'	=> htmlspecialchars($item['search_str']),
			'replace_str'	=> htmlspecialchars($item['replace_str']),
			'case'			=> intval($item['case']),
		);
	}
}

/**
* FrontEnd
*
* Module frontend
*
* @access public
*/
function usual(&$out) {
 $this->admin($out);
}
 function processSubscription($event, &$details) {
  $this->getConfig();
	 $level=$details['level'];
	 $message=$details['message'];
	 $destination = $details['destination'];
	 $filename       = md5($message) . '_google.mp3';
	 $cachedVoiceDir = ROOT . 'cms/cached/voice';
	 $cachedFileName = $cachedVoiceDir . '/' . $filename;


	 if (($event=='SAY' || $event=='SAYTO' || $event=='ASK') && !$this->config['DISABLED'] && !$details['ignoreVoice']) {

	if($this->config['EMPHASIS']) {
		$emphasis = SQLSelect('SELECT * FROM `google_tts_emphasis`');
		foreach($emphasis as $item) {
			if($item['case']) {
				$message = str_replace($item['search_str'], $item['replace_str'], $message);
			} else {
				$message = preg_replace_callback('/('.preg_quote($item['search_str'], '/').')/ui', function($match) use($item) {
					return $item['replace_str'];
				}, $message);
			}
		}
	}
	
    $accessKey=$this->config['ACCESS_KEY'];
	$languageCode=$this->config['LANGUAGECODE'];
	$voice=$this->config['VOICE'];
    
    if ($accessKey!='')
    {

        $base_url       = 'https://texttospeech.googleapis.com/v1beta1/text:synthesize?fields=audioContent&key='.$accessKey;
        if (!file_exists($cachedFileName))
        {

            $data = array("audioConfig"=> array(
                "audioEncoding"=> "LINEAR16",
                "pitch"=> '1',
                "speakingRate"=> '0'
            ),
                "input"=>array("text"=>$message),
                "voice"=>array("languageCode"=>$languageCode,"name"=> $voice)

            );

            $payload = json_encode( $data);
            try
            {
            $ch = curl_init($base_url);
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );
            curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            $result = curl_exec($ch);
            $contents = json_decode($result)->audioContent;
            curl_close($ch);
            }
           catch (Exception $e)
           {
              registerError('googletts', get_class($e) . ', ' . $e->getMessage());
           }
           if (isset($contents))
           {
              CreateDir($cachedVoiceDir);
              SaveFile($cachedFileName, base64_decode($contents));
           }
        } else {
         @touch($cachedFileName);
        }

        if (file_exists($cachedFileName)) {
		processSubscriptionsSafe('SAY_CACHED_READY', array(
				'level' => $level,
				'tts_engine' => 'google',
				'filename' => $cachedFileName,
				'destination' => $destination,
				'event' => $event,
				'message' => $message,
		));	
		if ($event=='SAY' && $level >= (int)getGlobal('minMsgLevel')) {
				playSound($cachedFileName, 1, $level);
			}
          //$details['ignoreVoice']=1;
			
        }
    }

  }
 }
/**
* Install
*
* Module installation routine
*
* @access private
*/
 function install($data='') {
  subscribeToEvent($this->name, 'SAY', '', 10);
	 subscribeToEvent($this->name, 'SAYTO');
	 subscribeToEvent($this->name, 'ASK');
  parent::install();
 }
 
 function dbInstall($data) {
$data = <<<EOD
 google_tts_emphasis: ID int(10) unsigned NOT NULL auto_increment
 google_tts_emphasis: search_str text
 google_tts_emphasis: replace_str text
 google_tts_emphasis: case boolean NOT NULL DEFAULT FALSE
EOD;
  parent::dbInstall($data);
 }
 
 function uninstall() {
  SQLExec('DROP TABLE IF EXISTS `google_tts_emphasis`');
	 unsubscribeFromEvent($this->name, 'SAY');
	 unsubscribeFromEvent($this->name, 'SAYTO');
	 unsubscribeFromEvent($this->name, 'ASK');
  parent::uninstall();
 }
 
// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgTWFyIDEzLCAyMDE2IHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
