(() => {
  'use strict';
  const key=document.querySelector('[data-geoapify-key]')?.dataset.geoapifyKey||'';
  window.SomiMaps={create(container,{onPick}={}) {
    const vi=document.documentElement.lang!=='en';
    if(!key||!window.L){container.textContent=vi?'Bản đồ chưa sẵn sàng. Bạn vẫn có thể tìm địa chỉ hoặc nhập tọa độ.':'Map unavailable. You can still search addresses or enter coordinates.';return null;}
    const map=L.map(container,{scrollWheelZoom:false}).setView([10.7769,106.7009],12);
    L.tileLayer('https://maps.geoapify.com/v1/tile/osm-carto/{z}/{x}/{y}.png?apiKey='+encodeURIComponent(key),{
      maxZoom:20,attribution:'Powered by <a href="https://www.geoapify.com/">Geoapify</a> | <a href="https://www.openstreetmap.org/copyright">© OpenStreetMap contributors</a>'
    }).addTo(map);
    let marker,route,revision=0;
    const pin=(lat,lng)=>{if(!Number.isFinite(Number(lat))||!Number.isFinite(Number(lng)))return;const pos=[Number(lat),Number(lng)];if(marker)marker.setLatLng(pos);else marker=L.circleMarker(pos,{radius:8,color:'#172a3a',fillColor:'#2b9b72',fillOpacity:1}).addTo(map);map.setView(pos,16);};
    if(onPick)map.on('click',e=>{revision++;onPick(e.latlng);});
    const observer=new ResizeObserver(()=>map.invalidateSize());observer.observe(container);
    return {pin,clear(){revision++;if(marker){marker.remove();marker=null;}if(route){route.remove();route=null;}},
      route(feature){if(route)route.remove();route=L.geoJSON(feature,{style:{color:'#248563',weight:5}}).addTo(map);map.fitBounds(route.getBounds(),{padding:[20,20]});},
      destroy(){revision++;observer.disconnect();map.remove();}};
  }};
})();
