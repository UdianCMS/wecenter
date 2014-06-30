<?php
/*
+--------------------------------------------------------------------------
|   WeCenter [#RELEASE_VERSION#]
|   ========================================
|   by WeCenter Software
|   © 2011 - 2013 WeCenter. All Rights Reserved
|   http://www.wecenter.com
|   ========================================
|   Support: WeCenter@qq.com
|
+---------------------------------------------------------------------------
*/


if (!defined('IN_ANWSION'))
{
	die;
}

class weixin extends AWS_ADMIN_CONTROLLER
{
	public function setup()
	{
		$this->crumb(AWS_APP::lang()->_t('微信'), 'admin/weixin/reply/');
	}

	public function reply_action()
	{
		TPL::assign('rule_list', $this->model('weixin')->fetch_reply_rule_list());
		TPL::assign('menu_list', $this->model('admin')->fetch_menu_list(801));

		TPL::output('admin/weixin/reply');
	}

	public function reply_add_action()
	{
		$this->crumb(AWS_APP::lang()->_t('添加规则'), "admin/weixin/reply_add/");

		TPL::assign('menu_list', $this->model('admin')->fetch_menu_list(801));

		TPL::output('admin/weixin/reply_edit');
	}

	public function reply_edit_action()
	{
		$this->crumb(AWS_APP::lang()->_t('编辑规则'), "admin/weixin/reply_add/");

		if (!$rule_info = $this->model('weixin')->get_reply_rule_by_id($_GET['id']))
		{
			H::redirect_msg(AWS_APP::lang()->_t('自定义回复规则不存在'));
		}

		TPL::assign('menu_list', $this->model('admin')->fetch_menu_list(801));
		TPL::assign('rule_info', $rule_info);

		TPL::output('admin/weixin/reply_edit');
	}

	public function mp_menu_action()
	{
		$account_id = $_GET['id'] ?: 0;

		$account_info = $this->model('weixin')->get_account_info_by_id($account_id);

		if (empty($account_info))
		{
			H::redirect_msg(AWS_APP::lang()->_t('微信账号不存在'));
		}

		if ($account_info['weixin_account_role'] == 'base' OR empty($account_info['weixin_app_id']) OR empty($account_info['weixin_app_secret']))
		{
			H::redirect_msg(AWS_APP::lang()->_t('此功能不适用于未通过微信认证的订阅号'));
		}

		$this->crumb(AWS_APP::lang()->_t('菜单管理'), 'admin/weixin/mp_menu/');

		$this->model('weixin')->client_list_image_clean($account_info['weixin_mp_menu']);

		TPL::assign('account_id', $account_info['id']);
		TPL::assign('mp_menu', $account_info['weixin_mp_menu']);
		TPL::assign('menu_list', $this->model('admin')->fetch_menu_list(803));

		TPL::assign('feature_list', $this->model('feature')->get_enabled_feature_list('id DESC', null, null));

		if (get_setting('category_enable') == 'Y')
		{
			TPL::assign('category_data', json_decode($this->model('system')->build_category_json('question'), true));
		}

		TPL::assign('reply_rule_list', $this->model('weixin')->fetch_unique_reply_rule_list());

		TPL::import_js('js/ajaxupload.js');
		TPL::import_js('js/md5.js');

		TPL::output('admin/weixin/mp_menu');
	}

	public function save_mp_menu_action()
	{
		$account_id = $_POST['id'] ?: 0;

		if ($_POST['button'])
		{
			if (!$mp_menu = $this->model('weixin')->process_mp_menu_post_data($_POST['button']))
			{
				H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('远程服务器忙,请稍后再试')));
			}
		}

		$this->model('weixin')->update_setting_or_account($account_id, array(
			'weixin_mp_menu' => $mp_menu
		));

		H::ajax_json_output(AWS_APP::RSM(null, 1, null));
	}

	public function rule_save_action()
	{
		if (!$_POST['title'])
		{
			H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请输入回应内容')));
		}

		if ($_POST['id'])
		{
			if (!$rule_info = $this->model('weixin')->get_reply_rule_by_id($_POST['id']))
			{
				H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('自定义回复规则不存在')));
			}

			if ($_FILES['image']['name'])
			{
				AWS_APP::upload()->initialize(array(
					'allowed_types' => 'jpg,jpeg,png',
					'upload_path' => get_setting('upload_dir') . '/weixin/',
					'is_image' => TRUE
				))->do_upload('image');


				if (AWS_APP::upload()->get_error())
				{
					switch (AWS_APP::upload()->get_error())
					{
						default:
							H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('错误代码') . ': ' . AWS_APP::upload()->get_error()));
						break;

						case 'upload_invalid_filetype':
							H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('文件类型无效')));
						break;
					}
				}

				if (! $upload_data = AWS_APP::upload()->data())
				{
					H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('上传失败, 请与管理员联系')));
				}

				AWS_APP::image()->initialize(array(
					'quality' => 90,
					'source_image' => $upload_data['full_path'],
					'new_image' => $upload_data['full_path'],
					'width' => 640,
					'height' => 320
				))->resize();

				AWS_APP::image()->initialize(array(
					'quality' => 90,
					'source_image' => $upload_data['full_path'],
					'new_image' => get_setting('upload_dir') . '/weixin/square_' . basename($upload_data['full_path']),
					'width' => 80,
					'height' => 80
				))->resize();

				unlink(get_setting('upload_dir') . '/weixin/' . $rule_info['image_file']);

				$rule_info['image_file'] = basename($upload_data['full_path']);
			}

			$this->model('weixin')->update_reply_rule($_POST['id'], $_POST['title'], $_POST['description'], $_POST['link'], $rule_info['image_file']);

			H::ajax_json_output(AWS_APP::RSM(array(
				'url' => get_js_url('/admin/weixin/reply/')
			), 1, null));
		}
		else
		{
			if (!$_POST['keyword'])
			{
				H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请输入关键词')));
			}

			if ($this->model('weixin')->get_reply_rule_by_keyword($_POST['keyword']) AND !$_FILES['image']['name'])
			{
				H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('已经存在相同的文字回应关键词')));
			}

			if ($_FILES['image']['name'])
			{
				AWS_APP::upload()->initialize(array(
					'allowed_types' => 'jpg,jpeg,png',
					'upload_path' => get_setting('upload_dir') . '/weixin/',
					'is_image' => TRUE
				))->do_upload('image');


				if (AWS_APP::upload()->get_error())
				{
					switch (AWS_APP::upload()->get_error())
					{
						default:
							H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('错误代码') . ': ' . AWS_APP::upload()->get_error()));
						break;

						case 'upload_invalid_filetype':
							H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('文件类型无效')));
						break;
					}
				}

				if (! $upload_data = AWS_APP::upload()->data())
				{
					H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('上传失败, 请与管理员联系')));
				}

				AWS_APP::image()->initialize(array(
					'quality' => 90,
					'source_image' => $upload_data['full_path'],
					'new_image' => $upload_data['full_path'],
					'width' => 640,
					'height' => 320
				))->resize();

				AWS_APP::image()->initialize(array(
					'quality' => 90,
					'source_image' => $upload_data['full_path'],
					'new_image' => get_setting('upload_dir') . '/weixin/square_' . basename($upload_data['full_path']),
					'width' => 80,
					'height' => 80
				))->resize();

				$image_file = basename($upload_data['full_path']);
			}

			$this->model('weixin')->add_reply_rule($_POST['keyword'], $_POST['title'], $_POST['description'], $_POST['link'], $image_file);
		}

		H::ajax_json_output(AWS_APP::RSM(array(
			'url' => get_js_url('admin/weixin/reply/')
		), 1, null));
	}

	public function accounts_action()
	{
		$accounts_list = $this->model('weixin')->fetch_page('weixin_accounts', null, 'id ASC', null, null);
		$accounts_total = $this->model('weixin')->found_rows();

		$this->crumb(AWS_APP::lang()->_t('微信账号'), "admin/weixin/accounts/");

		TPL::assign('menu_list', $this->model('admin')->fetch_menu_list(802));
		TPL::assign('accounts_list', $accounts_list);
		TPL::assign('accounts_total', $accounts_total);
		TPL::output('admin/weixin/accounts');
	}

	public function sent_msgs_list_action()
	{
		$msgs_list = $this->model('weixin')->fetch_page('weixin_msg', null, 'id DESC', $_GET['page'], $this->per_page);
		$msgs_total = $this->model('weixin')->found_rows();

		TPL::assign('pagination', AWS_APP::pagination()->initialize(array(
			'base_url' => get_js_url('/admin/weixin/sent_msgs_list/'),
			'total_rows' => $msgs_total,
			'per_page' => $this->per_page
		))->create_links());

		$this->crumb(AWS_APP::lang()->_t('群发列表'), "admin/weixin/sent_msgs_list/");

		TPL::assign('menu_list', $this->model('admin')->fetch_menu_list(804));
		TPL::assign('msgs_list', $msgs_list);
		TPL::assign('msgs_total', $msgs_total);

		TPL::output('admin/weixin/sent_msgs_list');
	}

	public function sent_msg_details_action()
	{
		$msg_details = $this->model('weixin')->get_msg_details_by_id($_GET['id']);

		if (!$msg_details)
		{
			H::redirect_msg(AWS_APP::lang()->_t('群发消息不存在'));
		}

		$this->crumb(AWS_APP::lang()->_t('查看群发消息'), "admin/weixin/sent_msg_details/");

		TPL::assign('menu_list', $this->model('admin')->fetch_menu_list(804));
		TPL::assign('msg_details', $msg_details);
		TPL::output('admin/weixin/sent_msg_details');
	}

	public function send_msg_batch_action()
	{
		if (get_setting('weixin_account_role') != 'service' OR empty(get_setting('weixin_app_id')) OR empty(get_setting('weixin_app_secret')))
		{
			H::redirect_msg(AWS_APP::lang()->_t('此功能只适用于通过微信认证的服务号'));
		}

		$groups = $this->model('weixin')->get_groups();

		if (!is_array($groups))
		{
			H::redirect_msg(AWS_APP::lang()->_t('获取微信分组失败, 错误信息:<br />%s', $groups));
		}

		$this->crumb(AWS_APP::lang()->_t('群发消息'), "admin/weixin/send_msg_batch/");

		TPL::assign('menu_list', $this->model('admin')->fetch_menu_list(804));
		TPL::assign('groups', $groups);
		TPL::output('admin/weixin/send_msg_batch');
	}

	public function qr_code_action()
	{
		if (get_setting('weixin_account_role') != 'service' OR empty(get_setting('weixin_app_id')) OR empty(get_setting('weixin_app_secret')))
		{
			H::redirect_msg(AWS_APP::lang()->_t('此功能只适用于通过微信认证的服务号'));
		}

		$qr_code_list = $this->model('weixin')->fetch_page('weixin_qr_code', 'ticket IS NOT NULL', 'scene_id ASC', $_GET['page'], $this->per_page);

		$qr_code_rows = $this->model('weixin')->found_rows();

		TPL::assign('pagination', AWS_APP::pagination()->initialize(array(
			'base_url' => get_js_url('/admin/weixin/qr_code/'),
			'total_rows' => $qr_code_rows,
			'per_page' => $this->per_page
		))->create_links());

		$this->crumb(AWS_APP::lang()->_t('二维码管理'), "admin/weixin/qr_code/");

		TPL::assign('menu_list', $this->model('admin')->fetch_menu_list(805));
		TPL::assign('qr_code_list', $qr_code_list);
		TPL::output('admin/weixin/qr_code');
	}
}