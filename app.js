const products = [
  { id: "nasi-ayam", partner: "Dapur Nusa", initials: "DN", sku: "DN-001", name: "Nasi Ayam Suwir", price: 15000, commission: 1500, qty: 24 },
  { id: "risol-mayo", partner: "Dapur Nusa", initials: "DN", sku: "DN-014", name: "Risol Mayo", price: 10000, commission: 1500, qty: 18 },
  { id: "lemper-ayam", partner: "Pawon Ibu", initials: "PI", sku: "PI-008", name: "Lemper Ayam", price: 10000, commission: 1500, qty: 12 },
  { id: "brownies", partner: "Manis Loka", initials: "ML", sku: "ML-003", name: "Brownies Kukus", price: 15000, commission: 1500, qty: 8 }
];
const state = { quantities: Object.fromEntries(products.map((product) => [product.id, product.qty])), selectedPartner: "all", file: null };
const money = (value) => `Rp${new Intl.NumberFormat("id-ID").format(value)}`;
const productList = document.querySelector("#product-list");
const emptyFilter = document.querySelector("#empty-filter");
const partnerSelect = document.querySelector("#partner-select");
const summaryTotal = document.querySelector("#summary-total");
const summaryProducts = document.querySelector("#summary-products");
const summaryPartners = document.querySelector("#summary-partners");
const summaryBreakdown = document.querySelector("#summary-breakdown");
const summaryStatus = document.querySelector("#summary-status");
const finalState = document.querySelector("#final-state");
const proofFile = document.querySelector("#proof-file");
const filePreview = document.querySelector("#file-preview");
const filePreviewName = document.querySelector("#file-preview-name");
const saveMessage = document.querySelector("#save-message");
const toast = document.querySelector("#toast");
const toastMessage = document.querySelector("#toast-message");

function visibleProducts() {
  return products.filter((product) => state.selectedPartner === "all" || product.partner.toLowerCase().replaceAll(" ", "-") === state.selectedPartner);
}

function renderProducts() {
  const visible = visibleProducts();
  productList.innerHTML = visible.map((product) => `
    <div class="product-row" data-product-id="${product.id}">
      <div class="product-main"><span class="product-avatar" aria-hidden="true">${product.initials}</span><div class="product-name"><strong>${product.name}</strong><small>${product.partner} · SKU ${product.sku}</small></div></div>
      <div class="product-price">${money(product.price)}</div><div class="product-commission">${money(product.commission)}</div>
      <div class="qty-control" aria-label="Jumlah ${product.name}"><button class="qty-button" type="button" data-action="decrease" data-id="${product.id}" aria-label="Kurangi ${product.name}">−</button><input class="qty-input" type="number" min="0" max="999" value="${state.quantities[product.id]}" data-id="${product.id}" aria-label="Jumlah ${product.name}" /><button class="qty-button" type="button" data-action="increase" data-id="${product.id}" aria-label="Tambah ${product.name}">+</button></div>
    </div>`).join("");
  emptyFilter.hidden = visible.length > 0;
  document.querySelector("#add-row-button").hidden = visible.length === 0;
  updateSummary();
}

function updateSummary() {
  const visible = visibleProducts();
  const total = visible.reduce((sum, product) => sum + (Number(state.quantities[product.id]) || 0), 0);
  const partnerNames = [...new Set(visible.filter((product) => Number(state.quantities[product.id]) > 0).map((product) => product.partner))];
  summaryTotal.textContent = total;
  summaryProducts.textContent = `${visible.filter((product) => Number(state.quantities[product.id]) > 0).length} produk`;
  summaryPartners.textContent = `${partnerNames.length} mitra`;
  const groups = visible.reduce((acc, product) => { const qty = Number(state.quantities[product.id]) || 0; acc[product.partner] = (acc[product.partner] || 0) + qty; return acc; }, {});
  summaryBreakdown.innerHTML = Object.entries(groups).map(([partner, qty]) => `<div class="breakdown-row"><span>${partner}</span><strong>${qty} unit</strong></div>`).join("");
}

function showToast(message) {
  toastMessage.textContent = message; toast.classList.add("show"); window.clearTimeout(showToast.timer);
  showToast.timer = window.setTimeout(() => toast.classList.remove("show"), 3400);
}

partnerSelect.addEventListener("change", (event) => { state.selectedPartner = event.target.value; renderProducts(); });
productList.addEventListener("click", (event) => {
  const button = event.target.closest("button[data-action]"); if (!button) return;
  const id = button.dataset.id; const current = Number(state.quantities[id]) || 0;
  state.quantities[id] = Math.max(0, current + (button.dataset.action === "increase" ? 1 : -1)); renderProducts();
});
productList.addEventListener("input", (event) => {
  if (!event.target.matches(".qty-input")) return;
  state.quantities[event.target.dataset.id] = Math.max(0, Math.min(999, Number(event.target.value) || 0)); updateSummary();
});
proofFile.addEventListener("change", () => {
  const [file] = proofFile.files; if (!file) return;
  if (file.size > 5 * 1024 * 1024) { showToast("Ukuran file melebihi 5 MB."); proofFile.value = ""; return; }
  state.file = file; filePreviewName.textContent = file.name; filePreview.hidden = false; saveMessage.textContent = "Bukti siap dilampirkan ke draft";
});
document.querySelector("#remove-file").addEventListener("click", () => { state.file = null; proofFile.value = ""; filePreview.hidden = true; saveMessage.textContent = "Perubahan tersimpan otomatis sebagai draft"; });
document.querySelector("#reset-button").addEventListener("click", () => {
  products.forEach((product) => { state.quantities[product.id] = 0; }); state.file = null; proofFile.value = ""; filePreview.hidden = true; finalState.hidden = true; summaryStatus.textContent = "Draft"; summaryStatus.classList.remove("final-badge"); saveMessage.textContent = "Draft dikosongkan"; renderProducts(); showToast("Draft barang masuk dikosongkan.");
});
document.querySelector("#save-button").addEventListener("click", () => {
  const total = products.reduce((sum, product) => sum + (Number(state.quantities[product.id]) || 0), 0);
  if (total === 0) { showToast("Isi minimal satu jumlah barang sebelum menyimpan."); return; }
  saveMessage.textContent = "Masuk Final siap untuk stok opname"; summaryStatus.textContent = "Siap opname"; summaryStatus.classList.add("final-badge"); finalState.hidden = false; showToast("Barang masuk tersimpan dan siap untuk stok opname.");
});
document.querySelector(".notice-close").addEventListener("click", (event) => event.currentTarget.closest(".notice").remove());
renderProducts();
