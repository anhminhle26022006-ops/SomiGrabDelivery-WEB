const assert=require('node:assert/strict'),fs=require('fs'),vm=require('vm');
const source=fs.readFileSync('public/assets/js/customer/maps.js','utf8');
function setup(key){const window={},events={},calls={tiles:[],pins:[],removed:0,disconnected:0};
 const map={setView(pos){calls.view=pos;return this;},on(name,fn){events[name]=fn;},invalidateSize(){},fitBounds(){calls.fit=true;},remove(){calls.removed++;}};
 const L={map:()=>map,tileLayer:(url,options)=>{calls.tiles.push({url,options});return {addTo(){}};},circleMarker:pos=>{calls.pins.push(pos);return {addTo(){return this;},setLatLng(pos){calls.pins.push(pos);},remove(){}};},geoJSON:feature=>({addTo(){calls.feature=feature;return this;},getBounds(){return [];},remove(){}})};
 vm.runInNewContext(source,{window,L,document:{documentElement:{lang:'vi'},querySelector:()=>({dataset:{geoapifyKey:key}})},ResizeObserver:class{observe(){}disconnect(){calls.disconnected++;}}});window.L=L;
 return {api:window.SomiMaps,events,calls};}
let env=setup(''),node={};assert.equal(env.api.create(node),null);assert.equal(env.calls.tiles.length,0);assert.ok(node.textContent);
env=setup('browser-key');let picked;const map=env.api.create({}, {onPick:p=>picked=p});
assert.match(env.calls.tiles[0].url,/maps.geoapify.com/);assert.ok(env.calls.tiles[0].options.attribution.includes('openstreetmap.org/copyright'));assert.ok(env.calls.tiles[0].options.attribution.includes('geoapify.com'));
map.pin('10.7','106.7');assert.equal(env.calls.pins[0][0],10.7);assert.equal(env.calls.pins[0][1],106.7);
map.pin('bad','106');assert.equal(env.calls.pins.length,1);
env.events.click({latlng:{lat:10.8,lng:106.8}});assert.equal(picked.lat,10.8);
map.route({type:'Feature'});assert.equal(env.calls.fit,true);map.destroy();assert.equal(env.calls.removed,1);assert.equal(env.calls.disconnected,1);
console.log('PASS: no-key fallback, Geoapify tiles, required attribution, coordinate order, invalid pin, click, route bounds and cleanup');
