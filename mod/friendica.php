<?php
/**
 * @file mod/friendica.php
 */

use Friendica\App;
use Friendica\Core\Addon;
use Friendica\Core\Config;
use Friendica\Core\Hook;
use Friendica\Core\L10n;
use Friendica\Core\System;
use Friendica\Database\DBA;
use Friendica\Module\Register;

function friendica_init(App $a)
{
	if (!empty($a->argv[1]) && ($a->argv[1] == "json")) {
		$register_policies = [
			Register::CLOSED  => 'REGISTER_CLOSED',
			Register::APPROVE => 'REGISTER_APPROVE',
			Register::OPEN    => 'REGISTER_OPEN'
		];

		$register_policy_int = intval(Config::get('config', 'register_policy'));
		if ($register_policy_int !== Register::CLOSED && Config::get('config', 'invitation_only')) {
			$register_policy = 'REGISTER_INVITATION';
		} else {
			$register_policy = $register_policies[$register_policy_int];
		}

		$condition = [];
		$admin = false;
		if (!empty(Config::get('config', 'admin_nickname'))) {
			$condition['nickname'] = Config::get('config', 'admin_nickname');
		}
		if (!empty(Config::get('config', 'admin_email'))) {
			$adminlist = explode(",", str_replace(" ", "", Config::get('config', 'admin_email')));
			$condition['email'] = $adminlist[0];
			$administrator = DBA::selectFirst('user', ['username', 'nickname'], $condition);
			if (DBA::isResult($administrator)) {
				$admin = [
					'name' => $administrator['username'],
					'profile'=> System::baseUrl() . '/profile/' . $administrator['nickname'],
				];
			}
		}

		$visible_addons = Addon::getVisibleList();

		Config::load('feature_lock');
		$locked_features = [];
		$featureLock = Config::get('config', 'feature_lock');
		if (isset($featureLock)) {
			foreach ($featureLock as $k => $v) {
				if ($k === 'config_loaded') {
					continue;
				}

				$locked_features[$k] = intval($v);
			}
		}

		$data = [
			'version'          => FRIENDICA_VERSION,
			'url'              => System::baseUrl(),
			'addons'           => $visible_addons,
			'locked_features'  => $locked_features,
			'explicit_content' => (int)Config::get('system', 'explicit_content', false),
			'language'         => Config::get('system','language'),
			'register_policy'  => $register_policy,
			'admin'            => $admin,
			'site_name'        => Config::get('config', 'sitename'),
			'platform'         => FRIENDICA_PLATFORM,
			'info'             => Config::get('config', 'info'),
			'no_scrape_url'    => System::baseUrl().'/noscrape'
		];

		header('Content-type: application/json; charset=utf-8');
		echo json_encode($data);
		exit();
	}
}

function friendica_content(App $a)
{
	$o = '<h1>Friendica</h1>' . PHP_EOL;
	$o .= '<p>';
	$o .= L10n::t('This is Friendica, version %s that is running at the web location %s. The database version is %s, the post update version is %s.',
		'<strong>' . FRIENDICA_VERSION . '</strong>', System::baseUrl(), '<strong>' . DB_UPDATE_VERSION . '</strong>',
		'<strong>' . Config::get("system", "post_update_version") . '</strong>');
	$o .= '</p>' . PHP_EOL;

	$o .= '<p>';
	$o .= L10n::t('Please visit <a href="https://friendi.ca">Friendi.ca</a> to learn more about the Friendica project.') . PHP_EOL;
	$o .= '</p>' . PHP_EOL;

	$o .= '<p>';
	$o .= L10n::t('Bug reports and issues: please visit') . ' ' . '<a href="https://github.com/friendica/friendica/issues?state=open">'.L10n::t('the bugtracker at github').'</a>';
	$o .= '</p>' . PHP_EOL;
	$o .= '<p>';
	$o .= L10n::t('Suggestions, praise, etc. - please email "info" at "friendi - dot - ca');
	$o .= '</p>' . PHP_EOL;

	$visible_addons = Addon::getVisibleList();
	if (count($visible_addons)) {
		$o .= '<p>' . L10n::t('Installed addons/apps:') . '</p>' . PHP_EOL;
		$sorted = $visible_addons;
		$s = '';
		sort($sorted);
		foreach ($sorted as $p) {
			if (strlen($p)) {
				if (strlen($s)) {
					$s .= ', ';
				}
				$s .= $p;
			}
		}
		$o .= '<div style="margin-left: 25px; margin-right: 25px; margin-bottom: 25px;">' . $s . '</div>' . PHP_EOL;
	} else {
		$o .= '<p>' . L10n::t('No installed addons/apps') . '</p>' . PHP_EOL;
	}

	if (Config::get('system', 'tosdisplay'))
	{
		$o .= '<p>'.L10n::t('Read about the <a href="%1$s/tos">Terms of Service</a> of this node.', System::baseurl()).'</p>';
	}

	$blocklist = Config::get('system', 'blocklist', []);
	if (!empty($blocklist)) {
		$o .= '<div id="about_blocklist"><p>' . L10n::t('On this server the following remote servers are blocked.') . '</p>' . PHP_EOL;
		$o .= '<table class="table"><thead><tr><th>' . L10n::t('Blocked domain') . '</th><th>' . L10n::t('Reason for the block') . '</th></thead><tbody>' . PHP_EOL;
		foreach ($blocklist as $b) {
			$o .= '<tr><td>' . $b['domain'] .'</td><td>' . $b['reason'] . '</td></tr>' . PHP_EOL;
		}
		$o .= '</tbody></table></div>' . PHP_EOL;
	}

	Hook::callAll('about_hook', $o);

	return $o;
}
