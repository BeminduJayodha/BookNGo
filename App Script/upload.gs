function doPost(e) {
  try {
    const invoiceNumber = e.parameter.invoice_number;
    const fileName = e.parameter.file_name;
    const fileData = e.parameter.file_data;

    if (!fileName || !fileData) {
      return ContentService.createTextOutput(JSON.stringify({ success: false, message: 'Missing file data.' }))
        .setMimeType(ContentService.MimeType.JSON);
    }

    // Convert Base64 to Blob
    const blob = Utilities.newBlob(Utilities.base64Decode(fileData), 'application/pdf', fileName);

    // Find the latest year folder inside "Finance -> Invoices"
    const financeFolderId = '1DP1ZnzcZ6slJeWM4g-ur2KywnfNaAkSG'; // "Finance" folder ID
    const financeFolder = DriveApp.getFolderById(financeFolderId);

    const invoiceFolders = financeFolder.getFoldersByName('Invoices');
    if (!invoiceFolders.hasNext()) {
      return ContentService.createTextOutput(JSON.stringify({ success: false, message: 'Invoices folder not found.' }))
        .setMimeType(ContentService.MimeType.JSON);
    }

    const invoicesFolder = invoiceFolders.next();
    const yearFolders = invoicesFolder.getFolders();

    let latestYear = 0;
    let latestYearFolder = null;

    while (yearFolders.hasNext()) {
      const folder = yearFolders.next();
      const folderName = folder.getName();
      const match = folderName.match(/(\d{4})/);

      if (match && parseInt(match[1], 10) > latestYear) {
        latestYear = parseInt(match[1], 10);
        latestYearFolder = folder;
      }
    }

    if (!latestYearFolder) {
      return ContentService.createTextOutput(JSON.stringify({ success: false, message: 'No year folder found.' }))
        .setMimeType(ContentService.MimeType.JSON);
    }

    // Save the file in the latest year folder
    const file = latestYearFolder.createFile(blob);

    return ContentService.createTextOutput(JSON.stringify({
      success: true,
      message: 'File uploaded successfully.',
      fileUrl: file.getUrl()
    })).setMimeType(ContentService.MimeType.JSON);

  } catch (error) {
    return ContentService.createTextOutput(JSON.stringify({ success: false, message: error.message }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}
