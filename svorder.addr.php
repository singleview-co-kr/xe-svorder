<?php
/**
 * @class  svorderAddr
 * @author singleview(root@singleview.co.kr)
 * @brief  svorderAddr class
 */
 // 해석이 어려운 주소
//충청남도 예산군 예산읍 산성공원2길 90 (충청남도 예산군 예산읍 산성리 333) 예림오피스텔X동 123호 (산성리)
//경기도 광주시 오포읍 창뜰윗길 333 (경기도 광주시 오포읍 능평리 333-45) 뉴파크뷰빌라(Z동) 333호 (능평리, 뉴파크뷰빌라)
//경기도 광주시 오포읍 마루들길 333 (경기도 광주시 오포읍 양벌리 333) 123동 123호 (양벌리, 대주파크빌제1차X블럭제333동) 

// 도 생략
//경기 구리시 수91동 주공아파트 123동 1234호
//경북 구미시 황상동 화진금봉타운9차아파트 123동 1234호  
//강원	원주시	무실동	무실이편한세상아파트	123-345
//경남	진주시	망경동	망경한보아파트	123동	345호
//경북	안동시	정하동	현진에버빌	123동	345호
//전북	군산시	소룡동	123번지	(유)소차공업사	보험간판보이는사무실	

// 광역시 생략
///울산 남구 여천동 123-1 ABC케미칼(주) 부설연구소
///광주	남구	봉선동	포스코아파트123동1234호
///대구	북구	읍내동	한양코스모스아파트	123/1234
///대전	서구	둔산0동	가람아파트	12동345호
///부산	사하구	신평동	123-456번지	12통	3반	4층
///인천

// 특별시 생략
// 서울	서초구	서초0동	1234-5	대우엘카티	123호
class svorderAddr extends svorder 
{
	//private $_g_nExtractElemCnt = 0;
	private $_g_sAddrRaw = null; // for debug
	private $_g_aAddrRaw = [];
	private $_g_aAddrParsed = ['do'=>null,'si'=>null,'gu_gun'=>null,'dong_myun_eup'=>null,'bunji_ri'=>null];
	private $_g_oAddrHeader = NULL;
	private $_g_aProvinceSynonym = ['서울'=>'서울특별시','서울시'=>'서울특별시','세종'=>'세종특별자치시','세종시'=>'세종특별자치시',
									'인천'=>'인천광역시','대전'=>'대전광역시','대구'=>'대구광역시','광주'=>'광주광역시','울산'=>'울산광역시','부산'=>'부산광역시',
									'경기'=>'경기도','충북'=>'충청북도','충남'=>'충청남도','경북'=>'경상북도','경남'=>'경상남도','전북'=>'전라북도','전남'=>'전라남도','강원'=>'강원도',
									'제주'=>'제주특별자치도','제주도'=>'제주특별자치도'];
	private $_g_aProvinceFullname = ['서울특별시'=>true,'서울시'=>true,'세종특별자치시'=>true,
									'인천광역시'=>true,'대전광역시'=>true,'대구광역시'=>true,'광주광역시'=>true,'울산광역시'=>true,'부산광역시'=>true,
									'경기도'=>true,'충청북도'=>true,'충청남도'=>true,'경상북도'=>true,'경상남도'=>true,'전라북도'=>true,'전라남도'=>true,'강원도'=>true,'제주특별자치도'=>true ];
	private $_g_aStandardizeMetropolis = ['서울특별시'=>'서울','세종특별자치시'=>'세종',
											'인천광역시'=>'인천','대전광역시'=>'대전','대구광역시'=>'대구','광주광역시'=>'광주','울산광역시'=>'울산','부산광역시'=>'부산'];
/**
 * @brief 생성자
 * 주문 주소를 BI가 해석할 수 있도록 해석
 **/
	public function svorderAddr() 
	{
	}
/**
 * @brief 해석기
 **/
	public function parse($sAddr) 
	{
		$this->_g_sAddrRaw = $sAddr;
		$this->_setSkeletonHeader();
		$sAddr = trim($sAddr);		
		if(strlen($sAddr)==0)
			return;
	
		$this->_g_aAddrRaw = explode(' ', $sAddr);
		if(is_numeric($this->_g_aAddrRaw[0])) // 첫 요소가 우편번호라면 제거
			array_shift($this->_g_aAddrRaw);

		$this->_validateProvinceName();
		if(!$this->_g_aProvinceFullname[$this->_g_aAddrRaw[0]]) // 첫 주소 요소가 시도 명칭이 아니면
			return;

		$this->_g_aAddrRaw = preg_replace("/[ #\&\+%@=\/\\\:;,\.'\"\^`~\_|\!\?\*$#<>()\[\]\{\}]/i", '', $this->_g_aAddrRaw); // - 이외 특수문자 제거
		$aTmpAddr = $this->_g_aAddrRaw;
		foreach( $aTmpAddr as $nIdx => $sAddr )
			$this->_extractAddrElem();
		return;
	}
/**
 * @brief 계층화된 주소 정보 반환
 **/
	public function getHeader() 
	{
		$this->_nullifyHeader();
		$this->_classifyHeader();
		return $this->_g_aAddrParsed;
	}
/**
 * @brief 계층화된 주소 정보 반환
 **/
	public function getExtendedColumn() 
	{
		return array_keys($this->_g_aAddrParsed);
	}
/**
* @brief for debug only
*/
	public function dumpInfo()
	{
		//if( $this->_g_oAddrHeader->sDong && $this->_g_oAddrHeader->sRi )
		{
			echo '<BR>최초 주소 정보:'.$this->_g_sAddrRaw.'<BR>';
			echo '<BR>정리된 주소 정보:';
			foreach( $this->_g_oAddrHeader as $sTitle=>$sVal)
			{
				if( $sTitle == 'sRo' || $sTitle == 'sRoNo' )
					continue;
				if( $sVal )
					echo $sTitle.'=>'.$sVal.' & ';
			}
			echo '<BR>';
		}
		
	}
/**
 * @brief 시,도 약어를 정식명칭으로 변환
 **/
	private function _validateProvinceName() 
	{
		$sProvinceFullname = $this->_g_aProvinceSynonym[$this->_g_aAddrRaw[0]];
		if( $sProvinceFullname )
			$this->_g_aAddrRaw[0] = $sProvinceFullname;
	}
/**
 * @brief 한국 주소는 도,시,(구),동,번지 / 도,시,(군),면/읍,리  로 분류되는 체제로 나뉨
 * 도, 시, 구/군, 동/면/읍, 번지/리
 * ['do'=>null,'si'=>null,'gu_gun'=>null,'dong_myun_eup'=>null,'bunji_ri'=>null];
 * _nullifyHeader() 실행 후임
 **/
	private function _classifyHeader() 
	{
		// 서울특별시, 세종특별시 등의 예외를 [도] 체제와 일치시킴
		foreach($this->_g_oAddrHeader as $sTitle => $sVal)
		{
			if( $sVal ) // 최상위 주소 요소만 추출
			{
				$sStandardizedMetropolis = $this->_g_aStandardizeMetropolis[$sVal];
				if( $sStandardizedMetropolis )
					$this->_g_aAddrParsed['do'] = $sStandardizedMetropolis;
				break;
			}
		}
		if( !$this->_g_aAddrParsed['do'] )
			$this->_g_aAddrParsed['do'] = $this->_g_oAddrHeader->sDo;

		$this->_g_aAddrParsed['si'] = $this->_g_oAddrHeader->sSi;

		$this->_g_aAddrParsed['gu_gun'] = $this->_g_oAddrHeader->sGu;
		if( !$this->_g_aAddrParsed['gu_gun'] )
			$this->_g_aAddrParsed['gu_gun'] = $this->_g_oAddrHeader->sGun;

		$this->_g_aAddrParsed['dong_myun_eup'] = $this->_g_oAddrHeader->sDong;
		if( !$this->_g_aAddrParsed['dong_myun_eup'] )
			$this->_g_aAddrParsed['dong_myun_eup'] = $this->_g_oAddrHeader->sMyun;
		if( !$this->_g_aAddrParsed['dong_myun_eup'] )
			$this->_g_aAddrParsed['dong_myun_eup'] = $this->_g_oAddrHeader->sEup;
		
		$this->_g_aAddrParsed['bunji_ri'] = $this->_g_oAddrHeader->sRi;
		if( !$this->_g_aAddrParsed['bunji_ri'] )
			$this->_g_aAddrParsed['bunji_ri'] = $this->_g_oAddrHeader->sBunji;
	}
/**
 * @brief set skeleton addr header
 **/
	private function _setSkeletonHeader()
	{
		// 입력값 초기화
        $this->_g_oAddrHeader = new stdClass();
		$this->_g_oAddrHeader->sDo = svorder::S_NULL_SYMBOL;
		$this->_g_oAddrHeader->sSi = svorder::S_NULL_SYMBOL;
		$this->_g_oAddrHeader->sGu = svorder::S_NULL_SYMBOL;
		$this->_g_oAddrHeader->sGun = svorder::S_NULL_SYMBOL;
		$this->_g_oAddrHeader->sDong = svorder::S_NULL_SYMBOL;
		$this->_g_oAddrHeader->sMyun = svorder::S_NULL_SYMBOL;
		$this->_g_oAddrHeader->sEup = svorder::S_NULL_SYMBOL;
		$this->_g_oAddrHeader->sRi = svorder::S_NULL_SYMBOL;
		$this->_g_oAddrHeader->sBunji = svorder::S_NULL_SYMBOL; // 동/리의 하위 번지
		//$this->_g_oAddrHeader->sRiMisc = svorder::S_NULL_SYMBOL; // 
		// 도로명 주소 지도 좌표 DB가 없어서 후순위
		$this->_g_oAddrHeader->sRo = svorder::S_NULL_SYMBOL;
		$this->_g_oAddrHeader->sRoNo = svorder::S_NULL_SYMBOL;

		// 해석 결과 저장공간 초기화
		$this->_g_aAddrParsed['do'] = null;
		$this->_g_aAddrParsed['si'] = null;
		$this->_g_aAddrParsed['gu_gun'] = null;
		$this->_g_aAddrParsed['dong_myun_eup'] = null;
		$this->_g_aAddrParsed['bunji_ri'] = null;
	}
/**
 * @brief 주소 요소 추출하여 할당
 **/
	private function _extractAddrElem()
	{
		if(count($this->_g_aAddrRaw))
		{
			$sCurrentElem = $this->_g_aAddrRaw[0];
			$sElem = mb_substr($sCurrentElem, -1);
			$bDuplicated = false;
			switch($sElem)
			{
				case '도': // 도 명칭을 만족해야 함
					if($this->_g_oAddrHeader->sDo == svorder::S_NULL_SYMBOL)
					{
						$sDoName = array_shift($this->_g_aAddrRaw);
						if( $this->_g_aProvinceFullname[$sDoName] )//도의 공식 명칭인지 확인 ex)세부주소의 여의도, 송도, 시세이도 등 회피
							$this->_g_oAddrHeader->sDo = $sDoName;
					}
					else
						$bDuplicated = true;
					break;
				case '시':
					if($this->_g_oAddrHeader->sSi == svorder::S_NULL_SYMBOL)
						$this->_g_oAddrHeader->sSi = array_shift($this->_g_aAddrRaw);
					else
						$bDuplicated = true;
					break;
				case '구':
					if($this->_g_oAddrHeader->sGu == svorder::S_NULL_SYMBOL)
						$this->_g_oAddrHeader->sGu = array_shift($this->_g_aAddrRaw);
					else
						$bDuplicated = true;
					break;
				case '군':
					if($this->_g_oAddrHeader->sGun == svorder::S_NULL_SYMBOL)
						$this->_g_oAddrHeader->sGun = array_shift($this->_g_aAddrRaw);
					else
						$bDuplicated = true;
					break;
				case '로':
				case '길':
					if($this->_g_oAddrHeader->sRo == svorder::S_NULL_SYMBOL)
					{
						$this->_g_oAddrHeader->sRo = array_shift($this->_g_aAddrRaw);
						$this->_g_oAddrHeader->sRoNo = array_shift($this->_g_aAddrRaw);
					}
					else
						$bDuplicated = true;
					break;
				case '동':
					// 이미 읍 면 정보 추출됬으면 이후의 동 정보는 무시
					if($this->_g_oAddrHeader->sEup == svorder::S_NULL_SYMBOL && $this->_g_oAddrHeader->sMyun == svorder::S_NULL_SYMBOL) 
					{
						if($this->_g_oAddrHeader->sDong == svorder::S_NULL_SYMBOL)
						{
							//$sKrOnlyElem = preg_replace("/[ #\&\+\-%@=\/\\\:;,\.'\"\^`~\_|\!\?\*$#<>()\[\]\{\}0-9a-z]/i", "", $sCurrentElem); // 한글만 남기고 제거
							$sKrRemovedElem = preg_replace("/[^\x20-\x7e]/", '', $sCurrentElem); // ASCII 범주 코드 영문+특수문자를 제외한 모든 문자를 null로 치환 

							if(strlen($sKrRemovedElem)==0)
							{
								$this->_g_oAddrHeader->sDong = array_shift($this->_g_aAddrRaw);
								$this->_g_oAddrHeader->sBunji = array_shift($this->_g_aAddrRaw);
							}
							else
								array_shift($this->_g_aAddrRaw); // 한글만 제거했는데 숫자영문특수기호가 남으면 아파트동호수이므로 버림
						}
						else
							$bDuplicated = true;
					}
					else
						$bDuplicated = true;
					break;
				case '가': // 예) 당산동2가, 한강로2가
					// 이미 읍 면 정보 추출됬으면 이후의 동 정보는 무시
					if($this->_g_oAddrHeader->sEup == svorder::S_NULL_SYMBOL && $this->_g_oAddrHeader->sMyun == svorder::S_NULL_SYMBOL)
					{
						if($this->_g_oAddrHeader->sDong == svorder::S_NULL_SYMBOL)
						{
							$sNumberClearedElem = preg_replace('/[0-9]+/', '', $sCurrentElem);
							$sLastElem = mb_substr($sNumberClearedElem, -2, 2, 'utf-8'); // 마지막 2번쨰에서 2글자를 추출함
							if( $sLastElem == '동가' || $sLastElem == '로가' )
							{
								$this->_g_oAddrHeader->sDong = array_shift($this->_g_aAddrRaw);
								$this->_g_oAddrHeader->sBunji = array_shift($this->_g_aAddrRaw);
							}
							else
								array_shift($this->_g_aAddrRaw); // 버림
						}
						else
							$bDuplicated = true;
					}
					else
						$bDuplicated = true;
					break;
				case '읍':
					// 이미 동 정보 추출됬으면 이후의 읍 면 정보는 무시
					if($this->_g_oAddrHeader->sDong == svorder::S_NULL_SYMBOL)
					{
						if($this->_g_oAddrHeader->sEup == svorder::S_NULL_SYMBOL)
							$this->_g_oAddrHeader->sEup = array_shift($this->_g_aAddrRaw);
						else
							$bDuplicated = true;
					}
					else
						$bDuplicated = true;
					break;
				case '면':
					// 이미 동 정보 추출됬으면 이후의 읍 면 정보는 무시
					if($this->_g_oAddrHeader->sDong == svorder::S_NULL_SYMBOL)
					{
						if($this->_g_oAddrHeader->sMyun == svorder::S_NULL_SYMBOL)
							$this->_g_oAddrHeader->sMyun = array_shift($this->_g_aAddrRaw);
						else
							$bDuplicated = true;
					}
					else
						$bDuplicated = true;
					break;
				case '리':
					if($this->_g_oAddrHeader->sDong == svorder::S_NULL_SYMBOL)
					{
						if($this->_g_oAddrHeader->sRi == svorder::S_NULL_SYMBOL)
						{
							$this->_g_oAddrHeader->sRi = array_shift($this->_g_aAddrRaw);
							$this->_g_oAddrHeader->sBunji = array_shift($this->_g_aAddrRaw);
						}
						else
							$bDuplicated = true;
					}
					else
						$bDuplicated = true;
					break;
				case '호':
					array_shift($this->_g_aAddrRaw); // 일반적으로 아파트 호수 버림
					break;
				default:
					array_shift($this->_g_aAddrRaw);//echo '처리 규칙이 없는 요소 버림: ('.array_shift($this->_g_aAddrRaw).')  ';
					break;
			}
			if( $bDuplicated )
				array_shift($this->_g_aAddrRaw); //echo '중복 가능 요소 버림: ('.array_shift($this->_g_aAddrRaw).')  ';
		}
	}
/**
 * @brief 저장 명령을 실행하기 위해 값 할당 후에도 svorder::S_NULL_SYMBOL이면 null로 변경
 **/
	private function _nullifyHeader()
	{
		foreach( $this->_g_oAddrHeader as $sTitle => $sVal)
		{
			if( $sVal == svorder::S_NULL_SYMBOL )
				$this->_g_oAddrHeader->$sTitle = null;
		}
	}
}
/* End of file svorder.addr.php */
/* Location: ./modules/svorder/svorder.addr.php */