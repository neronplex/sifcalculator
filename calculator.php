<?php
class CONFIG
{
	// 定数
	# データベースの接続情報
	const DSN = 'mysql:host=localhost;dbname=SIFCalculator';
	const DB_USER = 'SIFCalculator';
	const DB_PASSWORD = 'SIFCalculator';
}

class Controller
{
	// クラス変数
	# Service
	private $service = null;
	
	// コンストラクタ
	public function __construct(Service $class = null)
	{
		# Seiviceのインスタンスを生成
		$this->service = $class ? $class : new Service;
	}
	
	// initから見たエンドポイント
	public function calculator(array $POST)
	{
		$this->service->setter($POST)->validation()->calculation();
		return $this;
	}
	
	// ViewへJSONを出力する関数
	public function echoJSON()
	{
		header('Content-Type:text/javascript; charset=UTF-8');
		echo json_encode([
				'status' => $this->service->status,
				'exp' => $this->service->exp
		]);
	}
}

class Service
{
	// クラス変数
	# レアリティ
	private $rarity = null;
	# 覚醒済か否か
	private $is_plus = null;
	# 計算を開始するレベル
	private $lv = null;
	
	# 経験値テーブル
	private $exp_tables = array();
	# 計算の完了した経験値を格納
	private $exp = null;
	
	# バリデーター
	private $validator = null;
	# 出力されたステータスを格納
	private $status = null;
	
	// コンストラクタ
	public function __construct()
	{
		$this->validator = new Validator();
	}
	
	// 秘匿プロパティのゲッター
	public function __get($property)
	{
		return $this->$property;
	}
	
	// セッター
	public function setter(array $POST)
	{
		$this->rarity = $POST['rarity'];
		$this->is_plus = $POST['isplus'];
		$this->lv = $POST['lv'];
		
		return $this;
	}
	
	// バリデーションのエンドポイント
	public function validation()
	{
		try {
			$this->executeVaridateRarity($this->rarity)->executeValidateIsplus($this->is_plus)->executeVaridateLv($this->lv);
		} catch (Exception $e) {
			$this->status = 'invalid';
		}
		
		return $this;
	}
	
	// 経験値計算のエンドポイント
	public function calculation()
	{
		if ($this->status === 'invalid') {
			return; 
		} else {
			$this->plusSelector($this->rarity, $this->is_plus)->expTableSelector($this->rarity)->arrayRebuilder($this->exp_tables)->executeCalc($this->exp_tables, $this->lv);
		}
	}
	
	// 入力されたレアリティに関するバリデーションの手続き関数
	private function executeVaridateRarity($rarity)
	{
		$this->validator->isEmpty($rarity)->string($rarity)->rarity($rarity);
		return $this;
	}
	
	// 覚醒フラグに関するバリデーションの手続き関数
	private function executeValidateIsplus($isplus)
	{
		$this->validator->isEmpty($isplus)->string($isplus);
		return $this;
	}
	
	// 入力されたレベルに関するバリデーションの手続き関数
	private function executeVaridateLv($lv)
	{
		$this->validator->isEmpty($lv)->num($lv, '1', '3')->isZero($lv);
		return $this;
	}
	
	// 覚醒フラグに応じてレアリティを選択する関数
	private function plusSelector($rarity, $isplus)
	{
		if ($isplus === 'true') {
			$new_rarity = "$rarity" . '_plus';
		} else {
			$new_rarity = "$rarity" . '_normal';
		}
		$this->rarity = $new_rarity;
		
		return $this;
	}
	
	// 経験値テーブルを取り出す関数
	private function expTableSelector($rarity)
	{
		try {
			$dbh = new PDO(CONFIG::DSN, CONFIG::DB_USER, CONFIG::DB_PASSWORD);
			$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$sql = "SELECT * FROM $rarity";
			
			$stmt = $dbh->prepare($sql);
			$stmt->execute();
			$row = $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			$this->status = (isset($e)) ? 'dberror' : '';
		}
		
		if (isset($row)) {
			$this->exp_tables = $row;
		}
		
		return $this;
	}
	
	// データベースから取り出した関数を都合よく再構築する関数
	private function arrayRebuilder($array)
	{
		foreach ($array as $values) {
			$lv = $values['lv'];
			$exp = $values['exp'];
				
			$results["$lv"] = $exp;
		}
		$this->exp_tables = $results;
		
		return $this;
	}
	
	// 実際に経験値を計算する関数
	private function executeCalc($tables, $lv)
	{
		if ($this->status === 'dberror') {
			return;
		} else {
			$exp = 0;
			$count = count($tables);
			
			for ($i = $lv; $i <= $count; $i++) {
				$exp = $exp + $tables["$i"];
			}
			
			$this->status = 'success';
			$this->exp = $exp;
		}

		return $this;
	}
}

class Validator
{
	// 変数が空かを検証する関数
	public function isEmpty($var) 
	{
		if (empty($var)) {
			throw new Exception();
		}
		
		return $this;
	}
	
	// 値が文字列か否かを検証する関数
	public function string($value)
	{
		if (!(is_string($value))) {
			throw new Exception();
		}
		
		return $this;
	}
	
	// 値が論理値か否かを検証する関数
	public function boolean($value)
	{
		if (!(is_bool($value))) {
			throw new Exception();
		}
		
		return $this;
	}
	
	// 入力されたレベルが半角数字かを検証する関数
	public function num($value, $min, $max)
	{
		if (!(preg_match("/^\d{" . "$min,$max" . "}$/", $value))) {
			throw new Exception();
		}
	
		return $this;
	}
	
	// 入力されたレアリティが実在するかを検証する関数
	public function rarity($value)
	{
		$result = false;
		
		if ($value === 'N') {
			$result = true;
		}
		
		if ($value === 'R') {
			$result = true;
		}
		
		if ($value === 'SR') {
			$result = true;
		}
		
		if ($value === 'UR') {
			$result = true;
		}
		
		if (!($result === true)) {
			throw new Exception();
		}
		
		return $this;
	}
	
	// 0が入力されていないかを検証する関数
	public function isZero($value)
	{
		if ($value === '0') {
			throw new Exception();
		}
		
		return $this;
	}
}