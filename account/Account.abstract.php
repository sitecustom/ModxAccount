<?php

if(!defined('MODX_BASE_PATH')) {
	die('Unauthorized access.');
}

/**
 * Class Account
 *
 * @license GNU General Public License (GPL), http://www.gnu.org/copyleft/gpl.html
 * @author ko4inn <ko4inn@gmail.com>
 */
abstract class Account {
	protected $default_field = array(
		'user' => array(
			'username' => null,
			'password' => null
		),
		'attribute' => array(
			'fullname' => null,
			'email' => null,
			'phone' => null,
			'mobilephone' => null,
			'dob' => null,
			'gender' => null,
			'country' => null,
			'state' => null,
			'city' => null,
			'zip' => null,
			'fax' => null,
			'photo' => null,
			'comment' => null
		)
	);
	protected $error = array();
	protected $user;
	private $data = array();

	public function __construct($modx) {
		$this->modx = $modx;
	}

	public function __get($key) {
		return (isset($this->data[$key]) ? $this->data[$key] : null);
	}

	public function __set($key, $value) {
		$this->data[$key] = $value;
	}

	/**
	 * deBug code
	 * @param $str
	 */
	public function dbug($str) {
		print('<pre>');
		print_r($str);
		print('</pre>');
	}

	/**
	 * set token
	 * @return string
	 */
	protected function setToken() {
		if(empty($_SESSION['token'])) {
			$_SESSION['token'] = uniqid('');
		}
		return $_SESSION['token'];
	}

	/**
	 * trim/striptags/escape/
	 * @param $data
	 * @return array
	 */
	protected function clean($data) {
		if(is_array($data)) {
			foreach($data as $key => $value) {
				unset($data[$key]);
				$data[$key] = $this->clean($value);
			}
		} else {
			$data = trim($this->modx->stripTags($this->modx->db->escape($data)));
		}
		return $data;
	}

	/**
	 * mail validate
	 * @param $email
	 * @return bool
	 */
	protected function mail_validate($email) {
		return preg_match('/^[^@]+@.*.[a-z]{2,15}$/i', $email) == true;
	}

	/**
	 * phone validate
	 * @param $phone
	 * @return bool
	 */
	protected function phone_validate($phone) {
		return preg_match('/^\+?[7|8][\ ]?[-\(]?\d{3}\)?[\- ]?\d{3}-?\d{2}-?\d{2}$/', $phone) == true;
	}

	/**
	 * mail validate
	 * @param $date
	 * @return bool
	 */
	protected function date_validate($date) {
		return date('d-m-Y', strtotime($date)) == $date;
	}

	/**
	 * custom array keys to string
	 * @param $data
	 * @param array $parents
	 * @param array $delimiter
	 * @return array
	 */
	protected function array_keys_to_string($data, $parents = array(), $delimiter = array('', '.', '')) {
		$result = array();
		
		foreach($data as $key => $value) {
			$group = $parents;
			array_push($group, $key);
			if(is_array($value)) {
				$result = $this->array_keys_to_string($value, $group, $delimiter);
				continue;
			}
			if(!empty($parents)) {
				if(!empty($value)) {
					$result[$delimiter[0] . implode($delimiter[1], $group) . $delimiter[2]] = $value;
				}
				continue;
			}
		}
		
		return $result;
	}
	
	/**
	 * custom filed validate ajax
	 * @param $data
	 * @param array $parents
	 */
	protected function custom_field_validate_ajax($data, $parents = array()) {
		foreach($data as $key => $value) {
			$group = $parents;
			array_push($group, $key);
			if(is_array($value)) {
				$this->custom_field_validate_ajax($value, $group);
				continue;
			}
			if(!empty($parents)) {
				if(empty($value)) {
					$this->error['custom_field[' . implode('][', $group) . ']'] = 'Не заполнено.';
				}
				continue;
			}
		}
	}

	/**
	 * custom filed validate
	 * @param $data
	 * @return array|string
	 */
	protected function custom_field_validate($data) {
		if(is_array($data)) {
			foreach($data as $key => $value) {
				$data[$key] = $this->custom_field_validate($value);
				if(empty($data[$key])) {
					unset($data[$key]);
				} else {
					$this->error['custom_field'] = $data;
				}
			}
		} else {
			if(empty($data)) {
				$data = 'Не заполнено.';
			} else {
				$data = '';
			}
		}
		return $data;
	}

	/**
	 * login
	 */
	protected function login() {
		if($this->getID()) {
			$this->modx->db->update(array(
				'password' => empty($this->user['cachepwd']) ? $this->user['password'] : $this->user['cachepwd'],
				'cachepwd' => ''
			), $this->modx->getFullTableName('web_users'), 'id=' . $this->getID());

			$this->modx->db->update(array(
				'logincount' => ($this->user['logincount'] + 1),
				'lastlogin' => time(),
				'thislogin' => 1
			), $this->modx->getFullTableName('web_user_attributes'), 'id=' . $this->getID());
		}
	}

	/**
	 * login out
	 */
	public function logout() {
		if($this->getID()) {
			$this->modx->db->update(array(
				'lastlogin' => time(),
				'thislogin' => 0
			), $this->modx->getFullTableName('web_user_attributes'), 'id=' . $this->getID());
		}
		$this->SessionHandler('destroy');
	}
	
	/**
	 * view template
	 * @param $template
	 * @param array $data
	 * @return bool
	 * @internal param $date
	 */
	protected function view($template, $data = array()) {
		$file = MODX_BASE_PATH . $template;
		if(file_exists($file)) {
			extract($data);
			ob_start();
			require($file);
			$output = ob_get_contents();
			ob_end_clean();
		} else {
			trigger_error('Error: Could not load template ' . $file . '!');
			exit();
		}
		return $output;
	}

	/**
	 * create image
	 * @param $file
	 * @param string $filename
	 * @param string $path
	 * @return string
	 */
	protected function image($file, $filename = '', $path = '') {
		$url = '';
		$thumb_width = 100;
		$thumb_height = 100;

		if(file_exists($file)) {

			$info = getimagesize($file);
			$width = $info[0];
			$height = $info[1];
			$mime = isset($info['mime']) ? $info['mime'] : '';

			if($mime == 'image/gif') {
				$image = imagecreatefromgif($file);
			} else if($mime == 'image/png') {
				$image = imagecreatefrompng($file);
			} else if($mime == 'image/jpeg') {
				$image = imagecreatefromjpeg($file);
			} else {
				$image = imagecreatefromjpeg($file);
			}

			if(($width / $height) >= ($thumb_width / $thumb_height)) {
				$new_height = $thumb_height;
				$new_width = $width / ($height / $thumb_height);
			} else {
				$new_width = $thumb_width;
				$new_height = $height / ($width / $thumb_width);
			}

			$xpos = 0 - ($new_width - $thumb_width) / 2;
			$ypos = 0 - ($new_height - $thumb_height) / 2;

			$thumb = imagecreatetruecolor($thumb_width, $thumb_height);
			imagefilledrectangle($thumb, 0, 0, $thumb_width, $thumb_height, imagecolorallocate($thumb, 255, 255, 255));
			imagecopyresampled($thumb, $image, $xpos, $ypos, 0, 0, $new_width, $new_height, $width, $height);

			if(!file_exists($this->modx->config['rb_base_url'] . 'images/users')) {
				mkdir($this->modx->config['base_path'] . $this->modx->config['rb_base_url'] . 'images/users', 0755, true);
				if(!is_file(MODX_BASE_PATH . $this->modx->config['rb_base_url'] . 'images/users/.htaccess')) {
					file_put_contents(MODX_BASE_PATH . $this->modx->config['rb_base_url'] . 'images/users/.htaccess', "Header append Cache-Control \"no-store, no-cache, must-revalidate, max-age=0\"\nExpiresActive On\nExpiresDefault \"now\"");
				}
			}

			if(empty($filename)) {
				$filename = md5(filemtime($file));
			} else {
				//				$filename = $filename . '.' . substr(md5(filemtime($file)), 0, 6);
			}

			if(empty($path)) {
				$path = $this->modx->config['rb_base_url'] . 'images/users/';
			}

			$ext = '.jpg';
			$url = $path . $filename . $ext;
			$filename = $this->modx->config['base_path'] . $path . $filename . $ext;

			@unlink($file);

			imagejpeg($thumb, $filename, '100');
			imagedestroy($thumb);
			imagedestroy($image);

		} else {

			$this->error['photo'] = 'Ошибка создания изображения ' . $file . '.';

		}

		return $url;
	}

	/**
	 * add photo
	 * @param array $config
	 * @return string
	 */
	public function add_photo($config = array()) {
		$json = array();

		if($this->getID()) {
			if(!empty($_FILES['photo']['tmp_name'])) {
				$info = getimagesize($_FILES['photo']['tmp_name']);
				$types = array(
					'image/gif',
					'image/png',
					'image/jpeg',
					'image/jpg'
				);
				$size = 102400;

				if(!in_array($info['mime'], $types)) {
					$json['error'] = 'Выберите файл изображения. Неверный формат файла.';
				} else if($_FILES['photo']['size'] >= $size) {
					$json['error'] = 'Файл изображения превышает допустимые размеры.';
				} else {
					$path = $this->image($_FILES['photo']["tmp_name"], $this->user['email']);
					@unlink(MODX_BASE_PATH . $this->user['photo']);
					$this->modx->db->update(array(
						'photo' => $path
					), $this->modx->getFullTableName('web_user_attributes'), 'id=' . $this->getID());
					$json['name'] = basename($path);
					$json['path'] = $path;
				}
			}
		} else {
			if(!empty($_FILES['photo']['tmp_name'])) {
				$info = getimagesize($_FILES['photo']['tmp_name']);
				$types = array(
					'image/gif',
					'image/png',
					'image/jpeg',
					'image/jpg'
				);
				$size = 102400;

				if(!in_array($info['mime'], $types)) {
					$json['error'] = 'Выберите файл изображения. Неверный формат файла.';
				} else if($_FILES['photo']['size'] >= $size) {
					$json['error'] = 'Файл изображения превышает допустимые размеры.';
				} else {
					$path = $this->image($_FILES['photo']["tmp_name"], '', $this->modx->config['rb_base_url'] . 'cache/images/');
					$json['name'] = basename($path);
					$json['path'] = $path;
				}
			}
			if($config['controller'] != $config['controllerRegister']) {
				$json['redirect'] = $config['controllerRegister'];
			}
		}

		header('content-type: application/json');
		return json_encode($json);
	}

	/**
	 * delete photo
	 * @param $config
	 * @return string
	 */
	public function del_photo($config) {
		$json = array();

		if($this->getID()) {
			if(!empty($this->user['photo'])) {
				@unlink(MODX_BASE_PATH . $this->user['photo']);
				$this->modx->db->update(array(
					'photo' => ''
				), $this->modx->getFullTableName('web_user_attributes'), 'id=' . $this->getID());
			}
		} else {
			if(!empty($config['photo'])) {
				@unlink(MODX_BASE_PATH . $config['photo']);
			}
			if($config['controller'] != $config['controllerRegister']) {
				$json['redirect'] = $config['controllerRegister'];
			}
		}

		header('content-type: application/json');
		return json_encode($json);
	}

	/**
	 * SessionHandler
	 * Starts the user session on login success. Destroys session on error or logout.
	 *
	 * @param string $directive ('start' or 'destroy')
	 * @param string $cookieName
	 * @param bool $remember
	 * @author Raymond Irving
	 * @author Scotty Delicious
	 *
	 * remeber может быть числом в секундах
	 */
	protected function SessionHandler($directive, $cookieName = 'WebLoginPE', $remember = true) {
		switch($directive) {
			case 'start':
				if($this->getID()) {
					$_SESSION['webShortname'] = $this->user['username'];
					$_SESSION['webFullname'] = $this->user['fullname'];
					$_SESSION['webEmail'] = $this->user['email'];
					$_SESSION['webValidated'] = 1;
					$_SESSION['webInternalKey'] = $this->getID();
					$_SESSION['webValid'] = base64_encode($this->user['password']);
					$_SESSION['webUser'] = base64_encode($this->user['username']);
					$_SESSION['webFailedlogins'] = $this->user['failedlogincount'];
					$_SESSION['webLastlogin'] = $this->user['lastlogin'];
					$_SESSION['webnrlogins'] = $this->user['logincount'];
					$_SESSION['webUsrConfigSet'] = array();
					$_SESSION['webUserGroupNames'] = $this->getUserGroups();
					$_SESSION['webDocgroups'] = $this->getDocumentGroups();
					if($remember) {
						$cookieValue = md5($this->user['username']) . '|' . $this->user['password'];
						$cookieExpires = time() + (is_bool($remember) ? (60 * 60 * 24 * 365 * 5) : (int) $remember);
						setcookie($cookieName, $cookieValue, $cookieExpires, '/', ($this->modx->config['server_protocol'] == 'http' ? false : true), true);
					}
				}
				break;
			case 'destroy':
				if(isset($_SESSION['mgrValidated'])) {
					unset($_SESSION['webShortname']);
					unset($_SESSION['webFullname']);
					unset($_SESSION['webEmail']);
					unset($_SESSION['webValidated']);
					unset($_SESSION['webInternalKey']);
					unset($_SESSION['webValid']);
					unset($_SESSION['webUser']);
					unset($_SESSION['webFailedlogins']);
					unset($_SESSION['webLastlogin']);
					unset($_SESSION['webnrlogins']);
					unset($_SESSION['webUsrConfigSet']);
					unset($_SESSION['webUserGroupNames']);
					unset($_SESSION['webDocgroups']);

					setcookie($cookieName, '', time() - 60, '/');
				} else {
					if(isset($_COOKIE[session_name()])) {
						setcookie(session_name(), '', time() - 60, '/');
					}
					setcookie($cookieName, '', time() - 60, '/');
					session_destroy();
				}
				break;
		}
	}

	/**
	 * get user ID
	 * @return mixed
	 */
	protected function getID() {
		if(!empty($this->user['internalKey'])) {
			return $this->user['internalKey'];
		} else if($userid = $this->modx->getLoginUserID('web')) {
			$this->user = $this->modx->getWebUserInfo($userid);
			return $this->user['internalKey'];
		}
	}

	/**
	 * @return array
	 */
	private function getUserGroups() {
		$out = array();
		if($this->getID()) {
			$web_groups = $this->modx->getFullTableName('web_groups');
			$webgroup_names = $this->modx->getFullTableName('webgroup_names');

			$sql = "SELECT `ugn`.`name` FROM {$web_groups} as `ug`
                INNER JOIN {$webgroup_names} as `ugn` ON `ugn`.`id`=`ug`.`webgroup`
                WHERE `ug`.`webuser` = " . $this->getID();
			$sql = $this->modx->db->makeArray($this->modx->db->query($sql));

			foreach($sql as $row) {
				$out[] = $row['name'];
			}
		}
		return $out;
	}

	/**
	 * @return array
	 */
	private function getDocumentGroups() {
		$out = array();
		if($this->getID()) {
			$web_groups = $this->modx->getFullTableName('web_groups');
			$webgroup_access = $this->modx->getFullTableName('webgroup_access');

			$sql = "SELECT `uga`.`documentgroup` FROM {$web_groups} as `ug`
                INNER JOIN {$webgroup_access} as `uga` ON `uga`.`webgroup`=`ug`.`webgroup`
                WHERE `ug`.`webuser` = " . $this->getID();
			$sql = $this->modx->db->makeArray($this->modx->db->query($sql));

			foreach($sql as $row) {
				$out[] = $row['documentgroup'];
			}
		}
		return $out;
	}

	/**
	 * generate password
	 * @param int $length
	 * @return string
	 */
	protected function genPassword($length = 10) {
		return substr(str_shuffle("qazxswedcvfrtgbnhyujmkiolp1234567890QAZXSWEDCVFRTGBNHYUJMKIOLP"), 0, $length);
	}

	/**
	 * send mail
	 */
	protected function send($data, $tpl) {

		if(empty($tpl)) {
			return $this->error['tpl'] = 'Шаблон нисьма не найден.';
		}

		$emailsender = $this->modx->config['emailsender'];
		$emailsubject = $this->modx->config['emailsubject'];
		$site_name = $this->modx->config['site_name'];
		$site_url = $this->modx->config['site_url'];

		$message = str_replace('[+uid+]', (!empty($data['username']) ? $data['username'] : $data['email']), $tpl);
		$message = str_replace('[+pwd+]', $data['password'], $message);
		$message = str_replace('[+ufn+]', $data['fullname'], $message);
		$message = str_replace('[+sname+]', $site_name, $message);
		$message = str_replace('[+semail+]', $emailsender, $message);
		$message = str_replace('[+surl+]', $site_url . ltrim($data['controllerLogin'], '/'), $message);

		foreach($data as $name => $value) {
			$message = str_replace('[+post.' . $name . '+]', $value, $message);
		}

		// Bring in php mailer!
		require_once MODX_MANAGER_PATH . 'includes/controls/class.phpmailer.php';
		$mail = new PHPMailer();
		$mail->CharSet = $this->modx->config['modx_charset'];
		$mail->From = $emailsender;
		$mail->FromName = $site_name;
		$mail->Subject = $emailsubject;
		$mail->Body = $message;
		$mail->addAddress($data['email'], $data['fullname']);

		if(!$mail->send()) {
			$this->error['send_mail'] = 'Ошибка отправки письма.';
		}
	}
}
