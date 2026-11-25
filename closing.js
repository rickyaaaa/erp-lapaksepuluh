const systemGross = 1050000;
const fields = { cash: document.querySelector("#cash-input"), qris: document.querySelector("#qris-input"), expense: document.querySelector("#expense-input") };
const money = (value) => `Rp${new Intl.NumberFormat("id-ID").format(Math.abs(value))}`;
const $ = (id) => document.querySelector(id);
function updateClosing() { const cash = Number(fields.cash.value) || 0; const qris = Number(fields.qris.value) || 0; const expense = Number(fields.expense.value) || 0; const accountability = cash + qris + expense; const difference = accountability - systemGross; $("#cash-summary").textContent = money(cash); $("#qris-summary").textContent = money(qris); $("#expense-summary").textContent = money(expense); $("#accountability").textContent = money(accountability); $("#difference").textContent = `${difference < 0 ? "-" : difference > 0 ? "+" : ""}${money(difference)}`; $("#difference-status").textContent = difference === 0 ? "KLOP" : difference < 0 ? "MINUS" : "PLUS"; $("#difference-panel").classList.toggle("balanced", difference === 0); }
Object.values(fields).forEach((field) => field.addEventListener("input", updateClosing));
function showProof(inputId, previewId) { $(inputId).addEventListener("change", (event) => { const [file] = event.target.files; if (!file) return; $(previewId).hidden = false; $(previewId).innerHTML = `<span class="file-preview-icon">▧</span><span class="file-preview-name">${file.name}</span>`; }); }
showProof("#qris-proof", "#qris-file"); showProof("#deposit-proof", "#deposit-file");
$("#reset-closing").addEventListener("click", () => { fields.cash.value = 0; fields.qris.value = 0; fields.expense.value = 0; updateClosing(); $("#closing-message").textContent = "Draft dikosongkan"; });
$("#submit-closing").addEventListener("click", () => { if (!$("#qris-proof").files.length || !$("#deposit-proof").files.length) { $("#closing-message").textContent = "Unggah bukti QRIS dan bukti setoran sebelum submit"; return; } $("#closing-status").textContent = "Submitted"; $("#closing-message").textContent = "Closing tersubmit dan menunggu verifikasi"; $("#closing-toast").classList.add("show"); window.setTimeout(() => $("#closing-toast").classList.remove("show"), 3200); });
updateClosing();


