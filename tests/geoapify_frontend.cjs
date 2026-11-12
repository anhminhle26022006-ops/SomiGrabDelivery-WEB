const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict');
class Element {
 constructor(){this.children=[];this.attrs={};this.events={};this.value='';this.isConnected=true;this.readOnly=false;this.classList={toggle(){}};}
 append(...nodes){this.children.push(...nodes);}
 after(){} remove(){} replaceChildren(){this.children=[];}
 setAttribute(k,v){this.attrs[k]=v;} removeAttribute(k){delete this.attrs[k];}
 addEventListener(k,fn){this.events[k]=fn;} removeEventListener(k){delete this.events[k];}
 querySelectorAll(){return this.children.filter(x=>x.attrs.role==='option');}
}
const elements=[],document={createElement(){const e=new Element();elements.push(e);return e;}};
const window={},timers=new Map();let clock=0;
vm.runInNewContext(fs.readFileSync('public/assets/js/customer/places.js','utf8'),{window,document,crypto:{randomUUID:()=> '01234567-89ab-4cde-89ab-0123456789ab'},setTimeout:fn=>{timers.set(++clock,fn);return clock;},clearTimeout:id=>timers.delete(id)});
const flush=async()=>{const jobs=[...timers.values()];timers.clear();await Promise.all(jobs.map(f=>f()));};
const input=new Element(),calls=[],pending=[];let invalidations=0,selected;
const api=async(path,method,body)=>{calls.push(body);return new Promise(resolve=>pending.push(resolve));};
const lookup=window.SomiPlaces.attach(input,{api,onSelect:a=>selected=a,onInvalidate:()=>invalidations++});
(async()=>{
 input.value='ab';input.events.input();await flush();assert.equal(calls.length,0);
 input.value='old';input.events.input();const old=flush();
 input.value='intermediate';input.events.input();await flush();
 input.value='new address';input.events.input();const newer=flush();
 assert.equal(calls.length,1,'do not pile requests behind a slow provider');
 assert.equal(input.attrs['aria-busy'],'true','feedback is immediate while queued');
 pending[0]({items:[{text:'Old address',address:{address_detail:'Old'}}]});await new Promise(setImmediate);
 assert.equal(calls.length,2);assert.equal(calls[1].input,'new address','discard intermediate queued searches');
 pending[1]({items:[{text:'New address',address:{address_detail:'Selected address',latitude:'10.7',longitude:'106.7'}}]});await Promise.all([old,newer]);
 const list=elements[1];assert.equal(list.children[0].textContent,'New address');assert.equal(input.attrs['aria-expanded'],'true');
 input.events.keydown({key:'ArrowDown',preventDefault(){}});assert.equal(input.attrs['aria-activedescendant'],list.children[0].id);
 input.events.keydown({key:'Enter',preventDefault(){},stopPropagation(){}});await new Promise(setImmediate);
 assert.equal(calls.length,2,'selection must not consume another API request');
 assert.equal(input.value,'Selected address');assert.equal(selected.latitude,'10.7');assert.equal(input.attrs['aria-expanded'],'false');assert.equal(invalidations,4);
 input.value='new address';input.events.input();await flush();assert.equal(calls.length,2,'reuse cached successful query');assert.equal(input.attrs['aria-expanded'],'true');
 input.value='cancel';input.events.input();input.events.keydown({key:'Escape'});await flush();assert.equal(calls.length,2);
 input.value='error';input.events.input();const search=flush();pending[2]({items:[]});await new Promise(setImmediate);
 assert.equal(calls[3].action,'geocode','empty autocomplete automatically searches the full address');pending[3]({items:[{text:'Full address result',address:{address_detail:'Full address result',latitude:'10.7',longitude:'106.7'}}]});await search;assert.equal(input.attrs['aria-expanded'],'true','fallback suggestions appear without Enter');
 input.value='manual query';input.events.keydown({key:'Enter',preventDefault(){},stopPropagation(){}});assert.equal(calls[4].action,'geocode','Enter remains an optional full-address search');pending[4]({items:[]});await new Promise(setImmediate);
 input.value='blur';input.events.input();input.events.blur();await flush();assert.equal(calls.length,5);
 input.value='new address';input.events.focus();assert.equal(input.attrs['aria-expanded'],'true','focusing a populated input reopens cached suggestions');input.events.blur();
 input.events.compositionstart();input.value='Vietnamese input';input.events.input();await flush();assert.equal(calls.length,5,'do not search during IME composition');input.events.compositionend();const composition=flush();pending[5]({items:[]});await new Promise(setImmediate);pending[6]({items:[]});await composition;
 lookup.destroy();assert.equal(timers.size,0);assert.equal(input.events.input,undefined);
 console.log('PASS: automatic suggestions and fallback without Enter, focus reopening, one request in flight, latest query only, cache, IME, keyboard selection and cleanup');
})().catch(e=>{console.error(e);process.exitCode=1;});
