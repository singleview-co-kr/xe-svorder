<?php
/**
 * @class  svorderModel
 * @author singleview(root@singleview.co.kr)
 * @brief  svorderModel
 */
require_once(_XE_PATH_.'modules/svorder/svorder.view.php');
class svorderMobile extends svorderView
{
/**
 * @brief 
 **/
	function init()
	{
		$template_path = sprintf("%sm.skins/%s/",$this->module_path, $this->module_info->mskin);
		if(!is_dir($template_path)||!$this->module_info->mskin) 
		{
			$this->module_info->mskin = 'default';
			$template_path = sprintf("%sm.skins/%s/",$this->module_path, $this->module_info->mskin);
		}
		$this->setTemplatePath($template_path);
		Context::addJsFile('common/js/jquery.min.js');
		Context::addJsFile('common/js/xe.min.js');
	}
}
/* End of file svorder.mobile.php */
/* Location: ./modules/svorder/svorder.mobile.php */