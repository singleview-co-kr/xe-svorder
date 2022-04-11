��<?php
/**
 * @class  ecasoListen
 * @author singleview(root@singleview.co.kr)
 * @brief  listen to the ecaso server class
 */
class ecasoListen
{
	var $_g_sResponse='err:general_listen_failure';
	var $_g_oSvorderConfig;
	var $_g_aErrLog = Array();
/**
 * @brief 
 */
	public function ecasoListen( $oConfig, $sTrType ) 
	{
		$this->_g_oSvorderConfig = $oConfig;
		switch( $sTrType )
		{
			case 'set_invoice':
				$this->_setInvoiceByOrder();
				break;
			case 'confirm_cancel':
				$this->_confirmCancelByOrder();
				break;
			case 'close_delivery':
				$this->_closeDeliveryByOrder();
				break;
			case 'set_pre_delivry':
				$this->_setDeliveryPreparationByOrder();
				break;
			default:
				$this->_g_sResponse = 'err:invalid_listen_command'; 
				return;
		}
		return;
	}
/**
 * @brief 
 */
	public function getResponse()
	{
		if( count($this->_g_aErrLog) > 0 )
		{
			$this->_g_sResponse = '';
			foreach( $this->_g_aErrLog as $key => $val )
				$this->_g_sResponse .= $key.'=>'.$val.'<BR>';
		}
		return $this->_g_sResponse;
	}
/**
 * @brief 
 */
	private function _setDeliveryPreparationByOrder()
	{
debugPrint( 'ecaso_listen::_setDeliveryPreparationByOrder');
debugPrint( $_SERVER[REMOTE_ADDR]);

		$sSvOrderSrl = Context::get( 'sv_tr_no' );
		$sExtraOrderSrl = Context::get( 'extra_tr_no' );
		//$sShippingInvoice = Context::get( 'shipping_invoice' );
debugPrint( 'singleview_order_no:' );
debugPrint($sSvOrderSrl );
debugPrint( 'ext_order_no:' );
debugPrint( $sExtraOrderSrl );

		if( strlen( $sSvOrderSrl ) == 0 )
		{
			$this->_g_sResponse ='err:no sv order srl';
			return;
		}
		
		$aSvOrderSrl = unserialize( $sSvOrderSrl );
		if( !is_array( $aSvOrderSrl ) )
		{
			$this->_g_sResponse ='err:sv order srl is not array';
			return;
		}

		if(count($aSvOrderSrl)==0)
		{
			$this->_g_sResponse ='err:no sv order srl';;
			return;
		}

		if( strlen( $sExtraOrderSrl ) == 0 )
		{
			$this->_g_sResponse ='err:no extra order srl';
			return;
		}
		
		$aExtraOrderSrl = unserialize( $sExtraOrderSrl );
		if( !is_array( $aExtraOrderSrl ) )
		{
			$this->_g_sResponse ='err:extra order srl is not array';
			return;
		}

		if(count($aExtraOrderSrl)==0)
		{
			$this->_g_sResponse ='err:no extra order srl';
			return;
		}

		if( count( $aSvOrderSrl ) != count( $aExtraOrderSrl ) )
		{
			$this->_g_sResponse ='err:count of sv order and extra order not match';
			return;
		}

		foreach( $aSvOrderSrl as $key => $val )
		{
			$sSvOrderSrl = $val;
			$sExtraOrderSrl = $aExtraOrderSrl[$key];
			$args->order_srl = $sSvOrderSrl;
			$args->thirdparty_order_id = $sExtraOrderSrl;
			$output = executeQueryArray('svorder.getOrderInfoBySvThirdPartyOrderSrl', $args);
//var_dump($output);
//echo '<BR>';
			if( count($output->data) == 0 )
			{
				$this->_g_aErrLog[$sExtraOrderSrl]= 'err:invalid order srl';
				continue;
			}

			if( $output->data[0]->order_status != svorder::ORDER_STATE_PAID )//'2' ) // �ش� �ֹ��� �ԱݿϷ� ���°� �ƴϸ� ó�� ����
			{
				$this->_g_aErrLog[$sExtraOrderSrl]= 'err:not an paid order srl';
				continue;
			}

			unset( $args );

			$args->order_srl = $sSvOrderSrl;
			$args->order_status = svorder::ORDER_STATE_PREPARE_DELIVERY;//3; // �����ȣ�� �ԷµǸ� ����� ���·� ����
			$args->express_id = $sExpressId;

//var_Dump($args );
//echo '<BR>';
			$oSvorderAdminController = getAdminController('svorder');
			$output = $oSvorderAdminController->updateSingleOrderStatus( $sSvOrderSrl, $args );
			if( !$output->toBool() )
			{
				$this->_g_aErrLog[$sExtraOrderSrl]= 'err:db failure';
				continue;
			}
		}
debugPrint( 'ecaso_listen::_setDeliveryPreparationByOrder:ok' );
		$this->_g_sResponse = 'ok';//echo 'ok';
	}
/**
 * @brief 
 */
	private function _closeDeliveryByOrder()
	{
debugPrint( 'ecaso_listen::_closeDeliveryByOrder');
debugPrint( $_SERVER[REMOTE_ADDR]);

		$sSvOrderSrl = Context::get( 'sv_tr_no' );
		$sExtraOrderSrl = Context::get( 'extra_tr_no' );
debugPrint( 'singleview_order_no:' );
debugPrint( $sSvOrderSrl );
debugPrint( 'ext_order_no:' );
debugPrint( $sExtraOrderSrl );

		if( strlen( $sSvOrderSrl ) == 0 )
		{
			$this->_g_sResponse ='err:no sv order srl';
			return;
		}
		
		$aSvOrderSrl = unserialize( $sSvOrderSrl );
		if( !is_array( $aSvOrderSrl ) )
		{
			$this->_g_sResponse ='err:sv order srl is not array';
			return;
		}

		if(count($aSvOrderSrl)==0)
		{
			$this->_g_sResponse ='err:no sv order srl';
			return;
		}

		if( strlen( $sExtraOrderSrl ) == 0 )
		{
			$this->_g_sResponse ='err:no extra order srl';
			return;
		}
		
		$aExtraOrderSrl = unserialize( $sExtraOrderSrl );
		if( !is_array( $aExtraOrderSrl ) )
		{
			$this->_g_sResponse ='err:extra order srl is not array';
			return;
		}

		if(count($aExtraOrderSrl)==0)
		{
			$this->_g_sResponse ='err:no extra order srl';
			return;
		}

		if( count( $aSvOrderSrl ) != count( $aExtraOrderSrl ) )
		{
			$this->_g_sResponse ='err:count of sv order and extra order not match';
			return;
		}
		
		foreach( $aSvOrderSrl as $key => $val )
		{
			$sSvOrderSrl = $val;
			$sExtraOrderSrl = $aExtraOrderSrl[$key];
			$args->order_srl = $sSvOrderSrl;
			$args->thirdparty_order_id = $sExtraOrderSrl;
			$output = executeQueryArray('svorder.getOrderInfoBySvThirdPartyOrderSrl', $args);
//var_dump($output);
//echo '<BR>';
			if( count($output->data) == 0 )
			{
				$this->_g_aErrLog[$sExtraOrderSrl]= 'err:invalid order srl';
				continue;
			}
			if( $output->data[0]->order_status != svorder::ORDER_STATE_ON_DELIVERY )//'4' ) // �ش� �ֹ��� ��� �� ���°� �ƴϸ� ó�� ����
			{
				$this->_g_aErrLog[$sExtraOrderSrl]= 'err:not an on-delivery order srl';
				continue;
			}

			unset( $args );

			$args->order_srl = $sSvOrderSrl;
			$args->order_status = svorder::ORDER_STATE_DELIVERED;//'5'; // ��� �Ϸ�� ����

//var_Dump($args );
//echo '<BR>';
			//$oSvorderController = getController('svorder');
			//$output = $oSvorderController->updateOrderStatus( $sSvOrderSrl, $args );
			$oSvorderAdminController = getAdminController('svorder');
			$output = $oSvorderAdminController->updateSingleOrderStatus( $sSvOrderSrl, $args );
			if( !$output->toBool() )
			{
				$this->_g_aErrLog[$sExtraOrderSrl]= 'err:db failure';
				continue;
			}
		}
debugPrint( 'ecaso_listen::_closeDeliveryByOrder:ok');
		$this->_g_sResponse = 'ok';
	}
/**
 * @brief
 * ./svorder.admin.controller.php::procSvorderAdminCancelSettlement()�� ������ ����
 */
	private function _confirmCancelByOrder()
	{
debugPrint( 'ecaso_listen::_confirmCancelByOrder');
debugPrint( $_SERVER[REMOTE_ADDR]);

		$sSvOrderSrl = Context::get( 'sv_tr_no' );
		$sExtraOrderSrl = Context::get( 'extra_tr_no' );
debugPrint( 'singleview_order_no:' );
debugPrint( $sSvOrderSrl );
debugPrint( 'ext_order_no:' );
debugPrint( $sExtraOrderSrl );

		if( strlen( $sSvOrderSrl ) == 0 )
		{
			$this->_g_sResponse ='err:no sv order srl';
			return;
		}
		
		$aSvOrderSrl = unserialize( $sSvOrderSrl );
		if( !is_array( $aSvOrderSrl ) )
		{
			$this->_g_sResponse ='err:sv order srl is not array';
			return;
		}

		if(count($aSvOrderSrl)==0)
		{
			$this->_g_sResponse ='err:no sv order srl';
			return;
		}

		if( strlen( $sExtraOrderSrl ) == 0 )
		{
			$this->_g_sResponse ='err:no extra order srl';
			return;
		}
		
		$aExtraOrderSrl = unserialize( $sExtraOrderSrl );
		if( !is_array( $aExtraOrderSrl ) )
		{
			$this->_g_sResponse ='err:extra order srl is not array';
			return;
		}

		if(count($aExtraOrderSrl)==0)
		{
			$this->_g_sResponse ='err:no extra order srl';
			return;
		}

		if( count( $aSvOrderSrl ) != count( $aExtraOrderSrl ) )
		{
			$this->_g_sResponse ='err:count of sv order and extra order not match';
			return;
		}
		
		foreach( $aSvOrderSrl as $key => $val )
		{
			$sSvOrderSrl = $val;
			$sExtraOrderSrl = $aExtraOrderSrl[$key];
			$args->order_srl = $sSvOrderSrl;
			$args->thirdparty_order_id = $sExtraOrderSrl;
			$output = executeQueryArray('svorder.getOrderInfoBySvThirdPartyOrderSrl', $args);
//var_dump($output);
//echo '<BR>';
			if( count($output->data) == 0 )
			{
				$this->_g_aErrLog[$sExtraOrderSrl]= 'err:invalid order srl';
				continue;
			}
			//if( $output->data[0]->order_status != svorder::ORDER_STATE_ON_CANCELLING )//'E' ) // �ش� �ֹ��� ��� ��û ���°� �ƴϸ� ó�� ����
			//{
			//	$this->_g_aErrLog[$sExtraOrderSrl]= 'err:not a cancel-requested order srl';
			//	continue;
			//}

			unset( $args );

			$args->order_srl = $sSvOrderSrl;
			$args->order_status = svorder::ORDER_STATE_CANCELLED;//'A'; // ��� �Ϸ�� ����

//var_Dump($args );
//echo '<BR>';
			//$oSvorderController = getController('svorder');
			//$output = $oSvorderController->updateOrderStatus( $sSvOrderSrl, $args );
			$oSvorderAdminController = getAdminController('svorder');
			$output = $oSvorderAdminController->updateSingleOrderStatus( $sSvOrderSrl, $args );
			if( !$output->toBool() )
			{
				$this->_g_aErrLog[$sExtraOrderSrl]= 'err:db failure';
				continue;
			}

			////////////////
			$oSvorderModel = &getModel('svorder');   
			$oSvOrder = $oSvorderModel->getOrderInfo($sSvOrderSrl);
			if( count( $oSvOrder->checkout_promotion_info ) )
			{
debugPrint( 'rollbackConsumerBenefit' );
				$oSvPromotionController = &getController('svpromotion');
				$output = $oSvPromotionController->procSvprmotionRollbackUsedCoupon( $oSvOrder ); // �̰� �ѹ� ���θ�� ���� �����ϰ� ������ ������ �ϰ� ó��
				if( !$output->toBool() )
					return $output;
			}
			////////////////
		}
debugPrint( 'ecaso_listen::_confirmCancelByOrder:ok');
		$this->_g_sResponse = 'ok';
	}
/**
 * @brief 
 */
	private function _setInvoiceByOrder()
	{
debugPrint( 'ecaso_listen::_setInvoiceByOrder');
debugPrint( $_SERVER[REMOTE_ADDR]);

		$sSvOrderSrl = Context::get( 'sv_tr_no' );
		$sExtraOrderSrl = Context::get( 'extra_tr_no' );
		$sShippingInvoice = Context::get( 'shipping_invoice' );
debugPrint( 'singleview_order_no:' );
debugPrint( $sSvOrderSrl );
debugPrint( 'ext_order_no:' );
debugPrint( $sExtraOrderSrl );
debugPrint( 'invoice_no:' );
debugPrint( $sShippingInvoice );
		if( strlen( $sSvOrderSrl ) == 0 )
		{
			$this->_g_sResponse ='err:no sv order srl';
			return;
		}
		
		$aSvOrderSrl = unserialize( $sSvOrderSrl );
		if( !is_array( $aSvOrderSrl ) )
		{
			$this->_g_sResponse ='err:sv order srl is not array';
			return;
		}

		if(count($aSvOrderSrl)==0)
		{
			$this->_g_sResponse ='err:no sv order srl';;
			return;
		}

		if( strlen( $sExtraOrderSrl ) == 0 )
		{
			$this->_g_sResponse ='err:no extra order srl';
			return;
		}
		
		$aExtraOrderSrl = unserialize( $sExtraOrderSrl );
		if( !is_array( $aExtraOrderSrl ) )
		{
			$this->_g_sResponse ='err:extra order srl is not array';
			return;
		}

		if(count($aExtraOrderSrl)==0)
		{
			$this->_g_sResponse ='err:no extra order srl';
			return;
		}
		
		if( strlen( $sShippingInvoice ) == 0 )
		{
			$this->_g_sResponse ='err:no shipping invoices';
			return;
		}
		
		$aShippingInvoice = unserialize( $sShippingInvoice );
		if( !is_array( $aShippingInvoice ) )
		{
			$this->_g_sResponse ='err:shipping invoices is not array';
			return;
		}

		if(count($aShippingInvoice)==0)
		{
			$this->_g_sResponse ='err:no shipping invoices';
			return;
		}

		if( count( $aSvOrderSrl ) != count( $aExtraOrderSrl ) )
		{
			$this->_g_sResponse ='err:count of sv order and extra order not match';
			return;
		}
		
		if( count( $aSvOrderSrl ) != count( $aShippingInvoice ) )
		{
			$this->_g_sResponse ='err:count of sv order and shipping invoice not match';
			return;
		}
		
		$sExpressId = $this->_g_oSvorderConfig->default_delivery_company;
		
//echo 'delivery_company_name:'.$sExpressId.'<BR>';
		foreach( $aSvOrderSrl as $key => $val )
		{
			$sSvOrderSrl = $val;
			$sExtraOrderSrl = $aExtraOrderSrl[$key];
			$args->order_srl = $sSvOrderSrl;
			$args->thirdparty_order_id = $sExtraOrderSrl;
			$output = executeQueryArray('svorder.getOrderInfoBySvThirdPartyOrderSrl', $args);
			if( count($output->data) == 0 )
			{
				$this->_g_aErrLog[$sExtraOrderSrl]= 'err:invalid order srl';
				continue;
			}
			unset( $args );
			$oSvorderAdminController = getAdminController('svorder');
			$aInvoiceInfo = $oSvorderAdminController->parseInvoiceSerials( $aShippingInvoice[$key] );
			$args->invoice_no = $aInvoiceInfo['default'];
			$args->extra_invoice_no = $aInvoiceInfo['extra'];
			$args->order_srl = $sSvOrderSrl;
			$args->order_status = svorder::ORDER_STATE_ON_DELIVERY;//4; // �����ȣ�� �ԷµǸ� ����� ���·� ����
			$args->express_id = $sExpressId;

			// ���°�����, ���ȸ��, ������ȣ �����Ͱ� ������ ������Ʈ �ʿ�ġ �ʴ´�.
			if( !$args->express_id )
			{
				$this->_g_aErrLog[$sExtraOrderSrl]= 'err:no carrier id';
				continue;
			}
//var_dump( $args->invoice_no );
//var_dump( $args->extra_invoice_no );
			if( !$args->invoice_no )
			{
				$this->_g_aErrLog[$sExtraOrderSrl]= 'err:no invoice no';
				continue;
			}

//var_Dump($args );
//echo '<BR>';
			//$oSvorderController = getController('svorder');//////////
			//$output = $oSvorderController->updateOrderStatus( $sSvOrderSrl, $args );
			$oSvorderAdminController = getAdminController('svorder');
			$output = $oSvorderAdminController->updateSingleOrderStatus( $sSvOrderSrl, $args );
			if( !$output->toBool() )
			{
				$this->_g_aErrLog[$sExtraOrderSrl]= 'err:db failure';
				continue;
			}
		}
debugPrint( 'ecaso_listen::_setInvoiceByOrder:ok');		
		$this->_g_sResponse = 'ok';
	}
}