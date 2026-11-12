const assert=require('node:assert/strict'),fs=require('fs'),vm=require('vm');
const source=fs.readFileSync('public/assets/js/customer/maps.js','utf8');
function setup(key){const window={},events={},calls={tiles:[],pins:[],removed:0,disconnected:0};
 const map={setView(pos){calls.view=pos;return this;},on(name,fn){events[name]=fn;},invalidateSize(){},fitBounds(){calls.fit=true;},remove(){calls.removed++;}};
 const L={map:()=>map,tileLayer:(url,options)=>{calls.tiles.push({url,options});return {addTo(){return this;},on(name,fn){events[name]=fn;}};},circleMarker:pos=>{calls.pins.push(pos);return {addTo(){return this;},setLatLng(pos){calls.pins.push(pos);},bindTooltip(label){calls.label=label.textContent;},bringToFront(){calls.userRaised=true;},remove(){calls.markerRemoved=(calls.markerRemoved||0)+1;}};},geoJSON:feature=>({addTo(){calls.feature=feature;return this;},getBounds(){return [];},remove(){calls.routeRemoved=true;}})};
 vm.runInNewContext(source,{window,L,document:{documentElement:{lang:'vi'},querySelector:()=>({dataset:{geoapifyKey:key}}),createElement:()=>({})},ResizeObserver:class{observe(){}disconnect(){calls.disconnected++;}}});window.L=L;
 return {api:window.SomiMaps,events,calls};}
let env=setup(''),node={};assert.equal(env.api.create(node),null);assert.equal(env.calls.tiles.length,0);assert.ok(node.textContent);
env=setup('browser-key');let picked;const map=env.api.create({}, {onPick:p=>picked=p});
assert.match(env.calls.tiles[0].url,/maps.geoapify.com/);assert.ok(env.calls.tiles[0].options.attribution.includes('openstreetmap.org/copyright'));assert.ok(env.calls.tiles[0].options.attribution.includes('geoapify.com'));
map.pin('10.7','106.7');assert.equal(env.calls.pins[0][0],10.7);assert.equal(env.calls.pins[0][1],106.7);
map.pin('bad','106');assert.equal(env.calls.pins.length,1);
env.events.click({latlng:{lat:10.8,lng:106.8}});assert.equal(picked.lat,10.8);
map.route({type:'Feature'});assert.equal(env.calls.fit,true);
map.points([{id:'user',latitude:10.8,longitude:106.8,label:'You'},{id:'pickup',latitude:10.81,longitude:106.81,label:'A'},{id:'delivery',latitude:'',longitude:106,label:'Invalid'}]);assert.equal(env.calls.userRaised,true);assert.equal(env.calls.label,'A');
map.points([{id:'user',latitude:10.8,longitude:106.8,label:'You'}]);assert.equal(env.calls.markerRemoved,1,'stale pickup removed without clearing GPS');
map.clearRoute();assert.equal(env.calls.routeRemoved,true);map.destroy();assert.equal(env.calls.removed,1);assert.equal(env.calls.disconnected,1);
console.log('PASS: no-key fallback, Geoapify tiles, required attribution, coordinate order, invalid pin, click, route bounds and cleanup');
