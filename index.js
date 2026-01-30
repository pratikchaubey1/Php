const API = "api/banned-suppliers.php";

/* DATE FORMAT */
function formatDate(dateStr) {
  if (!dateStr) return "-";
  const d = new Date(dateStr);
  return d.toLocaleDateString("en-GB", {
    day: "2-digit",
    month: "long",
    year: "numeric",
  });
}

/* LOAD */
document.addEventListener("DOMContentLoaded", () => {
  loadData();

  const fileInput = document.getElementById("pdfFile");
  fileInput?.addEventListener("change", () => {
    if (fileInput.files.length > 0) {
      const sizeKB = (fileInput.files[0].size / 1024).toFixed(2);
      const display = document.getElementById("fileSizeDisplay");
      display.innerText = `File size: ${sizeKB} KB`;
      display.style.display = "block";
    }
  });
});

/* LOAD DATA */
function loadData() {
  fetch(API)
    .then((r) => r.json())
    .then((res) => {
      const rows = document.getElementById("rows");
      rows.innerHTML = "";

      if (!res.data || res.data.length === 0) {
        rows.innerHTML = `<div class="table-row">No data found</div>`;
        return;
      }

      res.data.forEach((s, i) => {
        rows.innerHTML += `
    <div class="table-row">

      <div class="supplier-col"
           onclick="openPDF(${s.id})"
           style="cursor:pointer;">

        <a class="supplier-link">
          ${i + 1}. ${s.supplierName}
        </a>

        <div class="supplier-address">
          ${s.supplierAddress.replaceAll(",", "<br>")}
          ${s.pdfPath
            ? `
            <span class="file-meta">
              (${s.fileSize} KB${s.fileCode ? `, ${s.fileCode}` : ""})
            </span>
            <img src="Media/pdf.png" class="pdf-inline-icon">
          `
            : ""
          }
        </div>
      </div>

      <div>${formatDate(s.bannedDate)}</div>
      <div>${s.bannedBy}</div>
      <div>
        ${s.banningPeriod}
        ${!isNaN(s.banningPeriod) ? " Years" : ""}
      </div>

    </div>
  `;
      });
    });
}

/* OPEN PDF */
function openPDF(id) {
  window.open(`${API}?id=${id}&pdf=1`, "_blank");
}

/* MODAL */
function openModal() {
  document.getElementById("modal").style.display = "flex";
}
function closeModal() {
  document.getElementById("modal").style.display = "none";
}

/* SAVE */
function saveSupplier(e) {
  e.preventDefault();
  const formData = new FormData(e.target);

  fetch(API, { method: "POST", body: formData })
    .then((r) => r.json())
    .then((res) => {
      if (res.success) {
        alert("Supplier Added Successfully");
        e.target.reset();
        closeModal();
        loadData();
      } else {
        alert(res.message || "Failed");
      }
    });
}
