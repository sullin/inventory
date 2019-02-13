<?php

require "config.php";

$response = array("ok"=>false, "message"=>"");
$respcode = 500;

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_DATABASE, DB_USER, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
	$response["message"] = "Failed connecting to DB";
	send_response();
	exit();
}

///////////////////////////////////////////////////////////////////////////////
$uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$pos = strrpos($uri, "/data.php/");
if ($pos === FALSE) {
	send_response();
	exit();
}
$uri = substr($uri, $pos);
handle_uri($_SERVER["REQUEST_METHOD"], $uri);

send_response();

///////////////////////////////////////////////////////////////////////////////
function send_response() {
	global $respcode, $response;
	http_response_code($respcode);
	if ($respcode == 200) {
		print json_encode($response);
	} else {
		print $response["message"];
	}
}

///////////////////////////////////////////////////////////////////////////////
function handle_uri($method, $uri) {
	global $respcode, $response;

	if ($method == "GET" && $uri == "/data.php/items") {
		items();
		return;
	}
	if ($method == "GET" && $uri == "/data.php/item") {
		item();
		return;
	}
	if ($method == "GET" && $uri == "/data.php/itemtransactions") {
		itemtransactions();
		return;
	}
	if ($method == "GET" && $uri == "/data.php/transactions") {
		transactions();
		return;
	}
	if ($method == "POST" && $uri == "/data.php/item") {
		additem();
		return;
	}
	if ($method == "POST" && $uri == "/data.php/transaction") {
		addtransaction();
		return;
	}

	$response["message"] = "Unknown request";
	$respcode = 404;
}

///////////////////////////////////////////////////////////////////////////////
function items() {
	global $respcode, $response;
	$filter = "";
	if (isset($_GET["filter"]) && isset($_GET["filter"]["code"])) {
		$filter = $_GET["filter"]["code"];
	}
	$page = 1;
	if (isset($_GET["page"])) {
		$page = intval($_GET["page"]);
	}
	$num = 10;
	if (isset($_GET["count"])) {
		$num = intval($_GET["count"]);
	}
	$sort = array("code" => "asc");
	if (isset($_GET["sorting"]) && is_array($_GET["sorting"])) {
		$sort = $_GET["sorting"];
	}

	try {
		$count = db_get_item_cnt($filter);
	} catch(PDOException $e) {
		$response["message"] = "Failed counting data";
		return;
	}

	try {
		$items = db_get_items($filter, $page, $num, $sort);
	} catch(PDOException $e) {
		$response["message"] = "Failed loading data";
		return;
	};

	$respcode = 200;
	$response["ok"] =  true;
	$response["items"] = $items;
	$response["total"] = $count;
}

function item() {
	global $respcode, $response;
	if (!isset($_GET["code"])) {
		$response["message"] = "Invalid item code";
		return;
	}

	try {
		$item = db_get_item_bycode($_GET["code"]);
	} catch(PDOException $e) {
		$response["message"] = "Failed loading data";
		return;
	}

	$respcode = 200;
	$response["ok"] =  true;
	$response["item"] = $item;
}

function additem() {
	global $respcode, $response;

	$data = json_decode(file_get_contents('php://input'), true);

	if (!isset($data["code"])) {
		$response["message"] = "Invalid item code";
		return;
	}
	if (!isset($data["name"])) {
		$response["message"] = "Invalid item name";
		return;
	}

	try {
		db_add_item($data["code"], $data["name"]);
	} catch(PDOException $e) {
		$response["message"] = "Failed adding item";
		return;
	}

	$respcode = 200;
	$response["ok"] =  true;
}

function itemtransactions() {
	global $respcode, $response;

	if (!isset($_GET["id"])) {
		$response["message"] = "Missing id";
		return;
	}
	$id = $_GET["id"];

	$filter = "";
	if (isset($_GET["filter"]) && isset($_GET["filter"]["comment"])) {
		$filter = $_GET["filter"]["comment"];
	}
	$page = 1;
	if (isset($_GET["page"])) {
		$page = intval($_GET["page"]);
	}
	$num = 10;
	if (isset($_GET["count"])) {
		$num = intval($_GET["count"]);
	}
	$sort = array("date" => "desc");
	if (isset($_GET["sorting"]) && is_array($_GET["sorting"])) {
		$sort = $_GET["sorting"];
	}

	try {
		$count = db_get_item_transaction_cnt($id, $filter);
	} catch(PDOException $e) {
		$response["message"] = "Failed counting data";
		return;
	}

	try {
		$trs = db_get_item_transactions($id, $filter, $page, $num, $sort);
	} catch(PDOException $e) {
		$response["message"] = "Failed loading data";
		return;
	}

	try {
		$quantity = db_get_item($id)['quantity'];
	} catch(PDOException $e) {
		$response["message"] = "Failed loading data";
		return;
	}

	$respcode = 200;
	$response["ok"] =  true;
	$response["transactions"] = $trs;
	$response["totalq"] = $quantity;
	$response["total"] = $count;

}

function transactions() {
	global $respcode, $response;

	$page = 1;
	if (isset($_GET["page"])) {
		$page = intval($_GET["page"]);
	}
	$num = 10;
	if (isset($_GET["count"])) {
		$num = intval($_GET["count"]);
	}
	$sort = array("date" => "desc");
	if (isset($_GET["sorting"]) && is_array($_GET["sorting"])) {
		$sort = $_GET["sorting"];
	}

	try {
		$count = db_get_transaction_cnt();
	} catch(PDOException $e) {
		$response["message"] = "Failed counting data";
		return;
	}

	try {
		$trs = db_get_transactions($page, $num, $sort);
	} catch(PDOException $e) {
		$response["message"] = "Failed loading data";
		return;
	}

	$respcode = 200;
	$response["ok"] =  true;
	$response["transactions"] = $trs;
	$response["total"] = $count;
}

function addtransaction() {
	global $respcode, $response;

	$data = json_decode(file_get_contents('php://input'), true);

	if (!isset($data["itemid"])) {
		$response["message"] = "Invalid item id";
		return;
	}

	if (!isset($data["quantity"])) {
		$response["message"] = "Invalid quantity";
		return;
	}

	$comment = "";
	if (isset($data["comment"])) {
		$comment = $data["comment"];
	}

	$date = "";
	if (isset($data["date"])) {
		$date = $data["date"];
	}

	try {
		db_add_transaction($data["itemid"], $data["quantity"], $date, $comment);
	} catch(PDOException $e) {
		$response["message"] = "Failed adding transaction";
		return;
	}

	$respcode = 200;
	$response["ok"] =  true;
}

///////////////////////////////////////////////////////////////////////////////
function db_get_item_cnt($filter) {
	global $pdo;

	$stmt = $pdo->prepare("SELECT count(*) as numrows FROM items WHERE code LIKE CONCAT(:filter, '%');");
	$stmt->bindValue(":filter", $filter);
	$stmt->execute();
	$row = $stmt->fetch();
	return $row['numrows'];
}

function db_get_items($filter, $page, $num, $sort) {
	global $pdo;
	$start = ($page-1) * $num;
	$items_per_page = $num;

	$sortcol = array_keys($sort)[0];
	$sortdir = $sort[$sortcol];

	$sortcols = ["code", "quantity", "created", "lastchanged"];
	if (!in_array($sortcol, $sortcols)) $sortcol = $sortcols[0];

	$sortdirs = ["asc", "desc"];
	if (!in_array($sortdir, $sortdirs)) $sortdir = $sortdirs[0];

	$stmt = $pdo->prepare("SELECT items.id as id, items.code as code, items.name as name, items.created as created, IFNULL(sum(transactions.quantity),0) as quantity, max(transactions.date) as lastchanged FROM items LEFT JOIN transactions ON items.id=transactions.itemid WHERE items.code LIKE CONCAT(:filter, '%') GROUP BY items.id ORDER BY $sortcol $sortdir LIMIT :start, :count;");
	$stmt->bindValue(":filter", $filter);
	$stmt->bindValue(":start", $start, PDO::PARAM_INT);
	$stmt->bindValue(":count", $num, PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	return $rows;
}

function db_get_item($id) {
	global $pdo;
	$stmt = $pdo->prepare("SELECT items.id as id, items.code as code, items.name as name, items.created as created, IFNULL(sum(transactions.quantity),0) as quantity, max(transactions.date) as lastchanged FROM items LEFT JOIN transactions ON items.id=transactions.itemid WHERE items.id=:id GROUP BY items.id;");
	$stmt->bindValue(":id", $id, PDO::PARAM_INT);
	$stmt->execute();
	$row = $stmt->fetch();

	return $row;
}

function db_get_item_bycode($code) {
	global $pdo;
	$stmt = $pdo->prepare("SELECT items.id as id, items.code as code, items.name as name, items.created as created, IFNULL(sum(transactions.quantity),0) as quantity, max(transactions.date) as lastchanged FROM items LEFT JOIN transactions ON items.id=transactions.itemid WHERE items.code=:code GROUP BY items.id;");
	$stmt->bindValue(":code", $code);
	$stmt->execute();
	$row = $stmt->fetch();

	return $row;
}

function db_add_item($code, $name) {
	global $pdo;

	$stmt = $pdo->prepare("INSERT INTO items SET code=:code, name=:name;");
	$stmt->bindValue(":code", $code);
	$stmt->bindValue(":name", $name);
	$stmt->execute();
}

function db_get_item_transaction_cnt($id, $filter) {
	global $pdo;

	$stmt = $pdo->prepare("SELECT count(*) as numrows FROM transactions WHERE itemid=:id AND comment LIKE CONCAT(:filter, '%');");
	$stmt->bindValue(":filter", $filter);
	$stmt->bindValue(":id", $id, PDO::PARAM_INT);
	$stmt->execute();
	$row = $stmt->fetch();
	return $row['numrows'];
}

function db_get_item_transactions($id, $filter, $page, $num, $sort) {
	global $pdo;
	$start = ($page-1) * $num;
	$items_per_page = $num;

	$sortcol = array_keys($sort)[0];
	$sortdir = $sort[$sortcol];

	$sortcols = ["date", "quantity", "comment"];
	if (!in_array($sortcol, $sortcols)) $sortcol = $sortcols[0];

	$sortdirs = ["asc", "desc"];
	if (!in_array($sortdir, $sortdirs)) $sortdir = $sortdirs[0];

	$stmt = $pdo->prepare("SELECT id, quantity, date, comment FROM transactions WHERE itemid=:id AND comment LIKE CONCAT(:filter, '%') ORDER BY $sortcol $sortdir LIMIT :start, :count;");
	$stmt->bindValue(":filter", $filter);
	$stmt->bindValue(":id", $id, PDO::PARAM_INT);
	$stmt->bindValue(":start", $start, PDO::PARAM_INT);
	$stmt->bindValue(":count", $num, PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	return $rows;
}

function db_get_transactions($page, $num, $sort) {
	global $pdo;
	$start = ($page-1) * $num;
	$items_per_page = $num;

	$sortcol = array_keys($sort)[0];
	$sortdir = $sort[$sortcol];

	$sortcols = ["date", "quantity", "comment", "id", "name"];
	if (!in_array($sortcol, $sortcols)) $sortcol = $sortcols[0];

	$sortdirs = ["asc", "desc"];
	if (!in_array($sortdir, $sortdirs)) $sortdir = $sortdirs[0];

	$stmt = $pdo->prepare("SELECT transactions.id as xid, items.id as id, items.code as code, items.name as name, transactions.quantity as quantity, transactions.date as date, transactions.comment as comment FROM transactions LEFT JOIN items ON items.id=transactions.itemid ORDER BY $sortcol $sortdir LIMIT :start, :count;");
	$stmt->bindValue(":start", $start, PDO::PARAM_INT);
	$stmt->bindValue(":count", $num, PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	return $rows;
}

function db_get_transaction_cnt() {
	global $pdo;

	$stmt = $pdo->prepare("SELECT count(*) as numrows FROM transactions;");
	$stmt->execute();
	$row = $stmt->fetch();
	return $row['numrows'];
}

function db_add_transaction($itemid, $quantity, $date, $comment) {
	global $pdo;

	$stmt = $pdo->prepare("INSERT INTO transactions SET itemid=:itemid, quantity=:quantity, date=:date, comment=:comment;");
	$stmt->bindValue(":itemid", $itemid, PDO::PARAM_INT);
	$stmt->bindValue(":quantity", $quantity, PDO::PARAM_INT);
	$stmt->bindValue(":date", $date);
	$stmt->bindValue(":comment", $comment);
	$stmt->execute();
}

