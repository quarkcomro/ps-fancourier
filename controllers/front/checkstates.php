<?php
/**
 * 2018-2021 GURU CODERS SRL
 *
 * NOTICE OF LICENSE
 *
 * DISCLAIMER
 *
 * YOU ARE NOT ALLOWED TO REDISTRIBUTE OR RESELL THIS FILE OR ANY OTHER FILE
 * USED BY THIS MODULE.
 *
 * @author    GURU CODERS SRL <stickyrst@gmail.com>
 * @copyright 2018-2021 GURU CODERS SRL
 * @license   Commercial
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Rc_FancourierCheckstatesModuleFrontController extends ModuleFrontController
{
    public function init()
    {
        if (Tools::getValue('rc_fancourier_token') != Tools::encrypt('rc_fancourier')) {
            exit('TOKEN ERROR');
        }
        Configuration::set('PS_SHOP_ENABLE', 1);
        parent::init();
    }

    public function initContent()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;
        parent::initContent();
        $days_to_check = (int) Configuration::get('RC_FANCOURIER_OS_DAYS');
        $awbsChanged = [];
        if ($days_to_check > 0) {
            $awbsToCheck = $this->module->getAwbsToCheck($days_to_check);
            $checkedAwbs = $this->module->checkAwbsStates($awbsToCheck);
            if ($checkedAwbs) {
                $awbsChanged = $this->module->changeAwbsState($checkedAwbs);
            }
            $this->module->checkTransfers();
        } else {
            exit('DAYS TO CHECK = 0');
        }

        $this->context->smarty->assign('awbsChanged', $awbsChanged);

        if (Tools::version_compare(_PS_VERSION_, '1.7', '<')) {
            $this->setTemplate('checkstates.tpl');
        } else {
            $this->context->smarty->assign('module_dir', _PS_MODULE_DIR_);
            $this->setTemplate('module:' . $this->module->name . '/views/templates/front/checkstates_17.tpl');
        }
    }
}
