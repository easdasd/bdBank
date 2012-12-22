<?php

class bdBank_ControllerAdmin_Bank extends XenForo_ControllerAdmin_Abstract {
	public function actionIndex() {
		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL, XenForo_Link::buildAdminLink('bank/history'));
	}
	
	public function actionHistory($isArchive = false) {
		// this code is very similar with bdBank_ControllerPublic_Bank::actionHistory()
		// please update that method if needed
		$bank = XenForo_Application::get('bdBank');
		
		$filters = $this->_input->filterSingle('filters', XenForo_Input::ARRAY_SIMPLE);

		$conditions = array(
			'archive' => $isArchive,
		);
		$fetchOptions = array(
			'join' => bdBank_Model_Bank::FETCH_USER,
			'order' => 'date',
			'direction' => 'desc',
		);
		
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$transactionPerPage = bdBank_Model_Bank::options('perPage');
		$linkParams = array();
		
		// sets pagination fetch options
		$fetchOptions['page'] = $page;
		$fetchOptions['limit'] = $transactionPerPage;
		
		// processes filters
		if (!empty($filters['username'])) {
			$user = $this->getModelFromCache('XenForo_Model_User')->getUserByName($filters['username']);
			if (!empty($user)) {
				$filters['username'] = $user['username'];
				$conditions['user_id'] = $user['user_id'];
				$linkParams['filters[username]'] = $user['username'];
			} else {
				throw new XenForo_Exception(new XenForo_Phrase('requested_user_not_found'), true);
			}
		}
		if (!empty($filters['amount']) AND !empty($filters['amount_operator'])) {
			$conditions['amount'] = array($filters['amount_operator'], $filters['amount']);
			$linkParams['filters[amount]'] = $filters['amount'];
			$linkParams['filters[amount_operator]'] = $filters['amount_operator'];
		} else {
			unset($filters['amount']);
			unset($filters['amount_operator']);
		}
		
		$transactions = $bank->getTransactions($conditions, $fetchOptions);
		$totalTransactions = $bank->countTransactions($conditions, $fetchOptions);
		
		$viewParams = array(
			'transactions' => $transactions,
			
			'page' => $page,
			'perPage' => $transactionPerPage,
			'total' => $totalTransactions,
			'linkParams' => $linkParams,
		
			'filters' => $filters,
		
			'isArchive' => $isArchive,
		);

		return $this->responseView(
			'bdBank_ViewAdmin_History',
			'bdbank_history',
			$viewParams
		);
	}
	
	public function actionArchive() {
		return $this->actionHistory(true);
	}
	
	public function actionTransfer() {
		$formData = $this->_input->filter(array(
			'receivers' => XenForo_Input::STRING,
			'amount' => XenForo_Input::INT,
			'comment' => XenForo_Input::STRING,
		));
		
		if ($this->_request->isPost()) {
			// process the transfer request
			// this code is very similar with bdBank_ControllerPublic_Bank::actionTransfer()
			
			$receiverUsernames = explode(',',$formData['receivers']);
			$userModel = $this->getModelFromCache('XenForo_Model_User');
			$receivers = array();
			foreach ($receiverUsernames as $username) {
				$username = trim($username);
				if (empty($username)) continue; 
				$receiver = $userModel->getUserByName($username);
				if (empty($receiver)) {
					return $this->responseError(new XenForo_Phrase('bdbank_transfer_error_receiver_not_found_x', array('username' => $username)));
				}
				$receivers[$receiver['user_id']] = $receiver;
			}
			if (count($receivers) == 0) {
				return $this->responseError(new XenForo_Phrase('bdbank_transfer_error_no_receivers', array('money' => new XenForo_Phrase('bdbank_money'))));
			}
			if ($formData['amount'] == 0) {
				return $this->responseError(new XenForo_Phrase('bdbank_transfer_error_zero_amount'));
			}
			
			$personal = bdBank_Model_Bank::getInstance()->personal();
			
			foreach ($receivers as $receiver) {
				$personal->give($receiver['user_id'], $formData['amount'], $formData['comment'], bdBank_Model_Bank::TYPE_ADMIN);
			}
			
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('bank/history'));
		} else {
			return $this->responseView('bdBank_ViewAdmin_Transfer', 'bdbank_transfer');
		}
	}
	
	protected function _preDispatch($action) {
		$this->assertAdminPermission('bdbank');
	}
}