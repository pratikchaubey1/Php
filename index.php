<!DOCTYPE html>
<html>
<head>
  <title>Banned Suppliers</title>
  <link rel="stylesheet" href="index.css">
</head>
<body>

<button class="add-btn" onclick="openModal()">+ Add Supplier</button>

<div class="table">
  <div class="table-head">
    <div>SUPPLIER</div>
    <div>BANNED DATE</div>
    <div>BANNED BY</div>
    <div>PERIOD</div>
  </div>
  <div id="rows"></div>
</div>

<div id="modal" class="modal">
  <div class="modal-box">
    <h3>Add Supplier</h3>

    
    <form onsubmit="saveSupplier(event)" enctype="multipart/form-data">

      <input name="supplierName" placeholder="Supplier Name" required />
      <textarea name="supplierAddress" placeholder="Address" required></textarea>
      <input name="bannedBy" placeholder="Banned By" required />
      <input type="text" name="banningPeriod" list="period-list" placeholder="Years or 'Until further orders'" required />
      <datalist id="period-list">
        <option value="Until further orders">
        <option value="1">
        <option value="2">
        <option value="3">
        <option value="5">
      </datalist>
      <label>Banned Date:</label>
      <input type="date" name="bannedDate" required />

      <label>Upload PDF:</label>
      <input type="file" id="pdfFile" name="pdfFile" accept=".pdf" />
      <small id="fileSizeDisplay" style="display:none;"></small>

      <label>File Code:</label>
      <input type="text" name="fileCode" placeholder="EN" maxlength="3" />

      <div class="actions">
        <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-save">Save</button>
      </div>
    </form>
  </div>
</div>

<script src="index.js"></script>
</body>
</html>
