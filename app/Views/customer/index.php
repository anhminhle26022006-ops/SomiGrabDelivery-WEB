<header class="workspace-header"><a class="brand" href="?page=home"><strong>SOMI</strong><span>DELIVERY</span></a><nav><a class="active" href="?page=customer">Dashboard</a><a href="#orders">My Orders</a></nav><div class="header-actions"><span class="user-pill">◉ Customer</span><button class="button button-dark button-small" type="button" data-signout>Sign out</button></div></header>
<main class="workspace">
  <div class="workspace-heading"><p class="eyebrow dark-eyebrow">CUSTOMER WORKSPACE</p><h1>Send a package</h1><p>Create an order, preview the fee, then send it to the matching network.</p></div>
  <div class="workspace-grid">
    <section class="panel order-panel"><div class="panel-heading"><div><p class="eyebrow dark-eyebrow">NEW DELIVERY</p><h2>Create delivery order</h2></div><span class="panel-symbol">↗</span></div>
      <form data-order-form>
        <div class="field"><label for="pickup">Pickup address</label><input id="pickup" name="pickup" required placeholder="Street, district, city"></div>
        <div class="field"><label for="dropoff">Drop-off address</label><input id="dropoff" name="dropoff" required placeholder="Street, district, city"></div>
        <div class="form-two"><div class="field"><label for="distance">Estimated distance (km)</label><input id="distance" name="distance" type="number" min="1" step="0.5" value="4" required></div><div class="field"><label for="weight">Package weight (kg)</label><input id="weight" name="weight" type="number" min="0.1" step="0.1" value="2" required></div></div>
        <div class="field"><label for="service">Delivery service</label><select id="service" name="service"><option value="STANDARD">Standard delivery</option><option value="EXPRESS">Express delivery</option></select></div>
        <div class="fee-preview"><span>Estimated delivery fee</span><strong data-fee>₫35,000</strong><small>Demo estimate: base fee ₫30,000 for first 3 km + ₫5,000 per additional km.</small></div>
        <button class="button button-dark button-full" type="submit">Send delivery request <span>↗</span></button>
      </form>
    </section>
    <aside class="side-stack"><article class="mini-feature"><span class="feature-icon">⌁</span><h3>Smart matching</h3><p>Offers are sent to nearby verified shippers.</p></article><article class="mini-feature"><span class="feature-icon">◎</span><h3>Live status</h3><p>Track the order from matching to completed.</p></article><article class="mini-feature"><span class="feature-icon">₫</span><h3>Transparent fee</h3><p>The estimated fee is shown before confirmation.</p></article></aside>
  </div>
  <section class="panel orders-panel" id="orders"><div class="panel-heading"><div><p class="eyebrow dark-eyebrow">DELIVERY ACTIVITY</p><h2>Recent orders</h2></div><span class="muted" data-order-count>2 orders</span></div><div class="table-wrap"><table><thead><tr><th>Order ID</th><th>Pickup</th><th>Drop-off</th><th>Fee</th><th>Status</th></tr></thead><tbody data-orders><tr><td>SOMI-240125</td><td>District 1</td><td>Binh Thanh</td><td>₫35,000</td><td><span class="status status-blue">IN TRANSIT</span></td></tr><tr><td>SOMI-240126</td><td>Phu Nhuan</td><td>District 3</td><td>₫42,000</td><td><span class="status status-amber">MATCHING</span></td></tr></tbody></table></div></section>
</main>
