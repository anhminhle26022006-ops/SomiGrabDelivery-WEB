<?php
declare(strict_types=1);
require_once __DIR__ . '/../_delivery.php';
$method = $_SERVER['REQUEST_METHOD'] ?? '';
delivery_run(['GET', 'POST', 'PUT', 'DELETE'], 'address.manage_own', $method !== 'GET', static function (PDO $db, int $owner) use ($method): array {
    $service = new CustomerAddressService($db);
    if ($method === 'GET') {
        $id = delivery_query_id('id', true);
        return [200, $id === null ? ['items' => $service->all($owner)] : ['address' => $service->get($owner, $id)]];
    }
    if ($method === 'DELETE') {
        $service->archive($owner, delivery_query_id());
        return [200, ['archived' => true]];
    }
    $input=delivery_body();
    if($method==='POST'&&isset($input['action'])){
        $action=$input['action'];$id=isset($input['address_id'])?DeliveryInput::id($input['address_id']):null;
        if($action==='archive'){DeliveryInput::only($input,['action','address_id']);if($id===null)DeliveryInput::invalid('address_id');$service->archive($owner,$id);return [200,['archived'=>true]];}
        if($action!=='save')DeliveryInput::invalid('action');unset($input['action'],$input['address_id']);
    }else $id=$method==='PUT'?delivery_query_id():null;
    return [$id===null?201:200,['address'=>$service->save($owner,$input,$id)]];
});
