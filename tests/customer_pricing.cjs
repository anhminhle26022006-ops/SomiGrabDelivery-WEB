const fs = require('fs'), vm = require('vm'), assert = require('assert/strict'), cp = require('child_process');
const approved = JSON.parse(cp.execFileSync('C:/xampp/php/php.exe', ['-r', "echo json_encode(require 'app/Config/delivery-pricing.php');"], {encoding:'utf8'}));
const t = (s,...args) => Array.isArray(s) ? s.reduce((out,part,i)=>out+part+(args[i]??''),'') : s;
const context = {window:{SomiI18n:{t,msg:t,currency:'VND',lang:'vi',money:String}},document:{body:{dataset:{userId:'test-only'}},getElementById:()=>({textContent:JSON.stringify(approved)})},
  localStorage:{getItem:()=>null,setItem(){}},Intl,Date,URLSearchParams,setTimeout,clearTimeout};
vm.createContext(context);vm.runInContext(fs.readFileSync('public/assets/js/delivery-price.js','utf8'),context);vm.runInContext(fs.readFileSync('public/assets/js/customer/store.js','utf8'),context);
const store=context.window.SomiStore;
const point={area:store.AREAS[0].name,detail:'Test only'};
for (const currency of ['VND','USD']) {
  const quote=store.quote(point,point,{weightKg:20,quantity:10,type:'FRAGILE'},currency);
  assert.equal(quote.ready,true);
  assert.equal(quote.lines.length,1,'combined charge includes distance and weight');
  assert.ok(quote.deliveryFee > store.quote(point,point,{weightKg:1,quantity:1,type:'DOCUMENT'},currency).deliveryFee,'weight affects price');
  assert.equal(quote.returnFee,approved.return_fee_minor[currency]);
  assert.equal(store.USD_RATE,undefined,'no invented exchange rate');
}
assert.ok(store.listOrders().every(order=>Number.isFinite(order.fees.delivery)),'demo seed has finite prices');
for(const [km,kg,currency,expected] of [['15','1','VND',68000],['20','1','VND',88000],['20','5','USD',459],['39.999','30','VND',235496]]) assert.equal(context.window.SomiDeliveryPrice.calculate(approved,km,kg,currency).total,expected);
assert.equal(context.window.SomiDeliveryPrice.calculate(approved,'4.5','3.5','USD',12000,11000).total,177);
assert.equal(context.window.SomiDeliveryPrice.calculate(approved,'4.5','3.5','VND',12000,11000).total,35970);
assert.throws(()=>context.window.SomiDeliveryPrice.calculate(approved,'40','1','VND'));
assert.throws(()=>context.window.SomiDeliveryPrice.calculate(approved,'20','30.001','VND'));
console.log('PASS: exact distance/weight prices, ceiling once, 40 km/30 kg boundaries, fixed return fees and independent currencies');
