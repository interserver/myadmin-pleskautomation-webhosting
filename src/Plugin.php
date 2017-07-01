<?php

namespace Detain\MyAdminPleskAutomation;

use Detain\MyAdminPleskAutomation\PPAConnector;
use Symfony\Component\EventDispatcher\GenericEvent;

class Plugin {

	public static $name = 'PleskAutomation Webhosting';
	public static $description = 'Allows selling of PleskAutomation Server and VPS License Types.  More info at https://www.netenberg.com/pleskautomation.php';
	public static $help = 'It provides more than one million end users the ability to quickly install dozens of the leading open source content management systems into their web space.  	Must have a pre-existing cPanel license with cPanelDirect to purchase a pleskautomation license. Allow 10 minutes for activation.';
	public static $module = 'webhosting';
	public static $type = 'service';


	public function __construct() {
	}

	public static function getHooks() {
		return [
			self::$module.'.settings' => [__CLASS__, 'getSettings'],
			self::$module.'.activate' => [__CLASS__, 'getActivate'],
			self::$module.'.reactivate' => [__CLASS__, 'getReactivate'],
		];
	}

	public static function getActivate(GenericEvent $event) {
		if ($event['category'] == SERVICE_TYPES_WEB_PPA) {
			myadmin_log(self::$module, 'info', 'PleskAutomation Activation', __LINE__, __FILE__);
			$serviceClass = $event->getSubject();
			$settings = get_module_settings(self::$module);
			$serverdata = get_service_master($serviceClass->getServer(), self::$module);
			$hash = $serverdata[$settings['PREFIX'].'_key'];
			$ip = $serverdata[$settings['PREFIX'].'_ip'];
			$extra = run_event('parse_service_extra', $serviceClass->getExtra(), self::$module);
			$hostname = $serviceClass->getHostname();
			if (trim($hostname) == '')
				$hostname = $serviceClass->getId().'.server.com';
			$password = website_get_password($serviceClass->getId());
			$username = get_new_webhosting_username($serviceClass->getId(), $hostname, $serviceClass->getServer());
			$ppaConnector = get_webhosting_ppa_instance($serverdata);
			$service_template_id = 46;
			if (!isset($data['name']) || trim($data['name']) == '') {
				$data['name'] = str_replace('@', ' ', $data['account_lid']);
			}
			list($first, $last) = explode(' ', $data['name']);
			$request_person = array(
				'first_name' => $first,
				'last_name' => $last,
				'company_name' => (isset($data['company']) ? $data['company'] : ''),
			);
			$request_address = array(
				'street_name' => (isset($data['address']) ? $data['address'] : ''),
				'address2' => (isset($data['address2']) ? $data['address2'] : ''),
				'zipcode' => (isset($data['zip']) ? $data['zip'] : ''),
				'city' => (isset($data['city']) ? $data['city'] : ''),
				'country' => convert_country_iso2($data['country']),
				'state' => (isset($data['state']) ? $data['state'] : ''),
			);
			$request_phone = array(
				'country_code' => '1',
				'area_code' => '',
				'phone_num' => (isset($data['phone']) ? $data['phone'] : ''),
				'ext_num' => '',
			);
			$request = array(
				'person' => $request_person,
				'address' => $request_address,
				'phone' => $request_phone,
				'email' => $data['account_lid'],
			);
			try {
				$result = $ppaConnector->addAccount($request);
				//echo "Result:";var_dump($result);echo "\n";
				PPAConnector::checkResponse($result);
			} catch (Exception $e) {
				echo 'Caught exception: '.$e->getMessage().PHP_EOL;
				myadmin_log(self::$module, 'info', 'addAccount Caught exception: '.$e->getMessage(), __LINE__, __FILE__);
			}
			request_log(self::$module, $serviceClass->getCustid(), __FUNCTION__, 'ppa', 'addAccount', $request, $result);
			$accountId = $result['result']['account_id'];
			if (!is_array($extra))
				$extra = [];
			$extra[0] = $accountId;
			$db = get_module_db(self::$module);
			$serExtra = $db->real_escape(myadmin_stringify($extra));
			$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_ip='{$ip}', {$settings['PREFIX']}_extra='{$serExtra}' where {$settings['PREFIX']}_id='{$serviceClass->getId()}'", __LINE__, __FILE__);
			myadmin_log(self::$module, 'info', "addAccount Got Account ID: {$accountId}", __LINE__, __FILE__);
			$request = array(
				'account_id' => $accountId,
				'auth' => array(
					'login' => $username,
					'password' => $password
				),
				'person' => $request_person,
				'address' => $request_address,
				'phone' => $request_phone,
				'email' => $data['account_lid'],
			);
			try {
				$result = $ppaConnector->addAccountMember($request);
				//echo "Result:";var_dump($result);echo "\n";
				PPAConnector::checkResponse($result);
			} catch (Exception $e) {
				echo 'Caught exception: '.$e->getMessage().PHP_EOL;
				myadmin_log(self::$module, 'info', 'addAccountMember Caught exception: '.$e->getMessage(), __LINE__, __FILE__);
			}
			request_log(self::$module, $serviceClass->getCustid(), __FUNCTION__, 'ppa', 'addAccountMember', $request, $result);
			$user_id = $result['result']['user_id'];
			$username = $db->real_escape($username);
			$extra[1] = $user_id;
			$serExtra = $db->real_escape(myadmin_stringify($extra));
			$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_ip='{$ip}', {$settings['PREFIX']}_extra='{$serExtra}', {$settings['PREFIX']}_username='{$username}' where {$settings['PREFIX']}_id='{$serviceClass->getId()}'", __LINE__, __FILE__);
			myadmin_log(self::$module, 'info', "addAccountMember Got Account ID: {$user_id}  Username: {$username}  Password: {$password}", __LINE__, __FILE__);
			$request = array(
				'account_id' => $accountId,
				'service_template_id' => $service_template_id,
			);
			try {
				$result = $ppaConnector->activateSubscription($request);
				//echo "Result:";var_dump($result);echo "\n";
				PPAConnector::checkResponse($result);
			} catch (Exception $e) {
				echo 'Caught exception: '.$e->getMessage().PHP_EOL;
				myadmin_log(self::$module, 'info', 'activatesubscription Caught exception: '.$e->getMessage(), __LINE__, __FILE__);
			}
			request_log(self::$module, $serviceClass->getCustid(), __FUNCTION__, 'ppa', 'activateSubscription', $request, $result);
			$subscriptoinId = $result['result']['subscription_id'];
			$extra[2] = $subscriptoinId;
			$serExtra = $db->real_escape(myadmin_stringify($extra));
			$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_ip='{$ip}', {$settings['PREFIX']}_extra='{$serExtra}', {$settings['PREFIX']}_username='{$username}' where {$settings['PREFIX']}_id='{$serviceClass->getId()}'", __LINE__, __FILE__);
			myadmin_log(self::$module, 'info', "activateSubscription Got Subscription ID: {$subscriptoinId}", __LINE__, __FILE__);
			/*
			  $request = array(
			  'subscription_id' => $subscriptoinId,
			  'get_resources' => TRUE,
			  );
			  $result = $ppaConnector->getSubscription($request);
			  echo "Result:";var_dump($result);echo "\n";
			  try {
				PPAConnector::checkResponse($result);
			  } catch (Exception $e) {
			  echo 'Caught exception: '.$e->getMessage().PHP_EOL;
			  }
			  print_r($result);
			 */
			$request = array(
				'new_webspace' => array(
					'sub_id' => $subscriptoinId,
					'domain' => $hostname,
					'resources' => array(
						array('rt_id' => 1000084), // plesk_integration Subscription
						array('rt_id' => 1000115), // pleskwebiis_hosting IIS Webspace
						array('rt_id' => 1000087), // plesk_db_hosting MySQL database
						//array('rt_id' => 1000091), // plesk_db_hosting Microsoft SQL database
						array('rt_id' => 1000152), // plesk_db_hosting Microsoft SQL database
						array('rt_id' => 1000132), // plesk__mail PostFix Mail
					),
				),
			);
			try {
				$result = $ppaConnector->{'pleskintegration.createWebspace'}($request);
				//echo "Result:";var_dump($result);echo "\n";
				PPAConnector::checkResponse($result);
			} catch (Exception $e) {
				echo 'Caught exception: '.$e->getMessage().PHP_EOL;
				myadmin_log(self::$module, 'info', 'createWebspace Caught exception: '.$e->getMessage(), __LINE__, __FILE__);
			}
			request_log(self::$module, $serviceClass->getCustid(), __FUNCTION__, 'ppa', 'createWebspace', $request, $result);
			$webspace_id = $result['result']['webspace_id'];
			$extra[3] = $webspace_id;
			$serExtra = $db->real_escape(myadmin_stringify($extra));
			$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_ip='{$ip}', {$settings['PREFIX']}_extra='{$serExtra}', {$settings['PREFIX']}_username='{$username}' where {$settings['PREFIX']}_id='{$serviceClass->getId()}'", __LINE__, __FILE__);
			myadmin_log(self::$module, 'info', "Got Website ID: {$webspace_id}", __LINE__, __FILE__);
			if (is_numeric($webspace_id)) {
				//myadmin_log(self::$module, 'info', "Success, Response: " . var_export($vesta->response, TRUE), __LINE__, __FILE__);;
				website_welcome_email($serviceClass->getId());
				$event['success'] = TRUE;
			} else {
				add_output('Error Creating Website');
				myadmin_log(self::$module, 'info', 'Failure, Response: '.var_export($result, TRUE), __LINE__, __FILE__);
				$event['success'] = FALSE;
			}
			/*
			  $request = array(
			  'subscription_id' => $subscriptoinId,
			  );
			  $result = $ppaConnector->removeSubscription($request);
			  //echo "Result:";var_dump($result);echo "\n";
			  try {
				PPAConnector::checkResponse($result);
			  } catch (Exception $e) {
			  echo 'Caught exception: '.$e->getMessage().PHP_EOL;
			  }
			  echo "Success Removing Subscription\n";
			  $request = array(
			  'account_id' => $accountId,
			  );
			  $result = $ppaConnector->removeAccount($request);
			  //echo "Result:";var_dump($result);echo "\n";
			  try {
				PPAConnector::checkResponse($result);
			  } catch (Exception $e) {
			  echo 'Caught exception: '.$e->getMessage().PHP_EOL;
			  }
			  echo "Success Removing Account.\n";
			 */
			$event->stopPropagation();
		}
	}

	public static function getReactivate(GenericEvent $event) {
		if ($event['category'] == SERVICE_TYPES_WEB_PPA) {
			$serviceClass = $event->getSubject();
			$settings = get_module_settings(self::$module);
			$serverdata = get_service_master($serviceClass->getServer(), self::$module);
			$hash = $serverdata[$settings['PREFIX'].'_key'];
			$ip = $serverdata[$settings['PREFIX'].'_ip'];
			$extra = run_event('parse_service_extra', $serviceClass->getExtra(), self::$module);
			if (sizeof($extra) == 0)
				function_requirements('get_plesk_info_from_domain');
				$extra = get_plesk_info_from_domain($serviceClass->getHostname());
			if (sizeof($extra) == 0) {
				$msg = 'Blank/Empty Plesk Subscription Info, Email support@interserver.net about this';
				dialog('Error', $msg);
				myadmin_log(self::$module, 'info', $msg, __LINE__, __FILE__);
				$event['success'] = FALSE;
			} else {
				list($accountId, $user_id, $subscriptoinId, $webspace_id) = $extra;
				function_requirements('get_webhosting_ppa_instance');
				$ppaConnector = get_webhosting_ppa_instance($serverdata);
				$request = ['subscription_id' => $subscriptoinId];
				$result = $ppaConnector->enableSubscription($request);
				try {
					PPAConnector::checkResponse($result);
				} catch (\Exception $e) {
					echo 'Caught exception: '.$e->getMessage().PHP_EOL;
				}
				myadmin_log(self::$module, 'info', 'enableSubscription Called got '.json_encode($result), __LINE__, __FILE__);
			}
			$event->stopPropagation();
		}
	}

	public static function getChangeIp(GenericEvent $event) {
		if ($event['category'] == SERVICE_TYPES_WEB_PPA) {
			$serviceClass = $event->getSubject();
			$settings = get_module_settings(self::$module);
			$pleskautomation = new PleskAutomation(FANTASTICO_USERNAME, FANTASTICO_PASSWORD);
			myadmin_log(self::$module, 'info', "IP Change - (OLD:".$serviceClass->get_ip().") (NEW:{$event['newip']})", __LINE__, __FILE__);
			$result = $pleskautomation->editIp($serviceClass->get_ip(), $event['newip']);
			if (isset($result['faultcode'])) {
				myadmin_log(self::$module, 'error', 'PleskAutomation editIp('.$serviceClass->get_ip().', '.$event['newip'].') returned Fault '.$result['faultcode'].': '.$result['fault'], __LINE__, __FILE__);
				$event['status'] = 'error';
				$event['status_text'] = 'Error Code '.$result['faultcode'].': '.$result['fault'];
			} else {
				$GLOBALS['tf']->history->add($settings['TABLE'], 'change_ip', $event['newip'], $serviceClass->get_ip());
				$serviceClass->set_ip($event['newip'])->save();
				$event['status'] = 'ok';
				$event['status_text'] = 'The IP Address has been changed.';
			}
			$event->stopPropagation();
		}
	}

	public static function getMenu(GenericEvent $event) {
		$menu = $event->getSubject();
		if ($GLOBALS['tf']->ima == 'admin') {
			$menu->add_link(self::$module, 'choice=none.reusable_pleskautomation', 'icons/database_warning_48.png', 'ReUsable PleskAutomation Licenses');
			$menu->add_link(self::$module, 'choice=none.pleskautomation_list', 'icons/database_warning_48.png', 'PleskAutomation Licenses Breakdown');
			$menu->add_link(self::$module.'api', 'choice=none.pleskautomation_licenses_list', 'whm/createacct.gif', 'List all PleskAutomation Licenses');
		}
	}

	public static function getRequirements(GenericEvent $event) {
		$loader = $event->getSubject();
		$loader->add_requirement('crud_pleskautomation_list', '/../vendor/detain/crud/src/crud/crud_pleskautomation_list.php');
		$loader->add_requirement('crud_reusable_pleskautomation', '/../vendor/detain/crud/src/crud/crud_reusable_pleskautomation.php');
		$loader->add_requirement('get_pleskautomation_licenses', '/../vendor/detain/myadmin-pleskautomation-webhosting/src/pleskautomation.inc.php');
		$loader->add_requirement('get_pleskautomation_list', '/../vendor/detain/myadmin-pleskautomation-webhosting/src/pleskautomation.inc.php');
		$loader->add_requirement('pleskautomation_licenses_list', '/../vendor/detain/myadmin-pleskautomation-webhosting/src/pleskautomation_licenses_list.php');
		$loader->add_requirement('pleskautomation_list', '/../vendor/detain/myadmin-pleskautomation-webhosting/src/pleskautomation_list.php');
		$loader->add_requirement('get_available_pleskautomation', '/../vendor/detain/myadmin-pleskautomation-webhosting/src/pleskautomation.inc.php');
		$loader->add_requirement('activate_pleskautomation', '/../vendor/detain/myadmin-pleskautomation-webhosting/src/pleskautomation.inc.php');
		$loader->add_requirement('get_reusable_pleskautomation', '/../vendor/detain/myadmin-pleskautomation-webhosting/src/pleskautomation.inc.php');
		$loader->add_requirement('reusable_pleskautomation', '/../vendor/detain/myadmin-pleskautomation-webhosting/src/reusable_pleskautomation.php');
		$loader->add_requirement('class.PleskAutomation', '/../vendor/detain/pleskautomation-webhosting/src/PleskAutomation.php');
		$loader->add_requirement('vps_add_pleskautomation', '/vps/addons/vps_add_pleskautomation.php');
	}

	public static function getSettings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_select_master(self::$module, 'Default Servers', self::$module, 'new_website_ppa_server', 'Default Plesk Automation Setup Server', NEW_WEBSITE_PPA_SERVER, SERVICE_TYPES_WEB_PPA);
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_webhosting_ppa', 'Out Of Stock Plesk Automation Webhosting', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_WEBHOSTING_PPA'), array('0', '1'), array('No', 'Yes',));
	}

}
