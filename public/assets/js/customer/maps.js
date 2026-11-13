(() => {
  'use strict';
  const key=document.querySelector('[data-geoapify-key]')?.dataset.geoapifyKey||'';
  window.SomiMaps={create(container,{onPick,onUnavailable}={}) {
    const vi=document.documentElement.lang!=='en';
    if(!key||!window.L){container.textContent=vi?'Bản đồ chưa sẵn sàng. Bạn vẫn có thể tìm địa chỉ hoặc nhập tọa độ.':'Map unavailable. You can still search addresses or enter coordinates.';return null;}
    const map=L.map(container,{scrollWheelZoom:false}).setView([10.7769,106.7009],12);
    const tiles=L.tileLayer('https://maps.geoapify.com/v1/tile/osm-carto/{z}/{x}/{y}.png?apiKey='+encodeURIComponent(key),{
      maxZoom:20,attribution:'Powered by <a href="https://www.geoapify.com/">Geoapify</a> | <a href="https://www.openstreetmap.org/copyright">© OpenStreetMap contributors</a>'
    }).addTo(map);
    tiles?.on?.('tileerror',()=>onUnavailable?.());
    let marker,route,revision=0;
    const markers=new Map();
    const valid=p=>p&&p.latitude!==''&&p.longitude!==''&&p.latitude!=null&&p.longitude!=null&&Number.isFinite(Number(p.latitude))&&Number.isFinite(Number(p.longitude))&&Math.abs(Number(p.latitude))<=90&&Math.abs(Number(p.longitude))<=180;
    const fit=()=>{const positions=[...markers.values()].map(m=>m.position),bounds=route?.getBounds();if(bounds?.getSouthWest){positions.push(bounds.getSouthWest(),bounds.getNorthEast());}if(positions.length>1)map.fitBounds(positions,{padding:[35,35],maxZoom:16});else if(positions.length)map.setView(positions[0],16);};
    const pin=(lat,lng)=>{if(!Number.isFinite(Number(lat))||!Number.isFinite(Number(lng)))return;const pos=[Number(lat),Number(lng)];if(marker)marker.setLatLng(pos);else marker=L.circleMarker(pos,{radius:8,color:'#172a3a',fillColor:'#2b9b72',fillOpacity:1}).addTo(map);map.setView(pos,16);};
    if(onPick)map.on('click',e=>{revision++;onPick(e.latlng);});
    const observer=new ResizeObserver(()=>map.invalidateSize());observer.observe(container);
    return {pin,points(items){
      const previousSize=markers.size;const keep=new Set();for(const item of items){if(!valid(item))continue;keep.add(item.id);const position=[Number(item.latitude),Number(item.longitude)];let entry=markers.get(item.id);if(entry){entry.layer.setLatLng(position);entry.position=position;}else{const color=item.id==='user'?'#2877e3':item.id==='delivery'?'#237657':'#14284b';const layer=L.circleMarker(position,{radius:item.id==='user'?8:10,color:item.id==='user'?'#fff':color,weight:3,fillColor:color,fillOpacity:1}).addTo(map);const label=document.createElement('span');label.textContent=item.label;layer.bindTooltip?.(label,{permanent:true,direction:item.id==='user'?'bottom':'top',offset:[0,item.id==='user'?10:-10]});markers.set(item.id,{layer,position});}}
      for(const [id,entry] of markers)if(!keep.has(id)){entry.layer.remove();markers.delete(id);}markers.get('user')?.layer.bringToFront?.();if(previousSize!==markers.size)fit();
    },clearRoute(){if(route){route.remove();route=null;}},clear(){revision++;if(marker){marker.remove();marker=null;}if(route){route.remove();route=null;}},
      route(feature){if(route)route.remove();route=L.geoJSON(feature,{style:{color:'#248563',weight:5}}).addTo(map);if(markers.size)fit();else map.fitBounds(route.getBounds(),{padding:[20,20]});},
      destroy(){revision++;observer.disconnect();map.remove();}};
  }};
})();
