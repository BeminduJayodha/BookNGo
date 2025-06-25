function listAllSheetsInAccountsFolder() { 
  const parentFolderId = '1309_5LuHNHotOqTYF9A4WOMs5vWfwH5L'; // Accounts folder ID
  const parentFolder = DriveApp.getFolderById(parentFolderId);

  const masterSheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName('ListofAccounts');
  masterSheet.clearContents(); // Clear previous data
  masterSheet.appendRow(['File Name', 'File URL', 'Year', 'File ID']); // Add headers

  const masterFileId = SpreadsheetApp.getActiveSpreadsheet().getId(); // Exclude current master file
  const files = parentFolder.getFiles(); // Get all files

  let fileData = [];

  while (files.hasNext()) {
    const file = files.next();

    if (file.getMimeType() === MimeType.GOOGLE_SHEETS && file.getId() !== masterFileId) {
      const fileName = file.getName();
      const fileUrl = file.getUrl();
      const fileId = file.getId();

      let year = '';
      const yearMatch = fileName.match(/(20\d{2})/);
      if (yearMatch) {
        year = parseInt(yearMatch[1]);
      }

      fileData.push([fileName, `=HYPERLINK("${fileUrl}", "${fileName}")`, year, fileId]);
    }
  }

  if (fileData.length === 0) {
    SpreadsheetApp.getUi().alert('No Google Sheets files found in the folder.');
    return;
  }

  // Sort by year descending
  fileData.sort((a, b) => b[2] - a[2]);

  // Write sorted data to sheet
  masterSheet.getRange(2, 1, fileData.length, fileData[0].length).setValues(fileData);
}

function doGet() {
  const ss = SpreadsheetApp.openById('1maVtj-WUt-9vlRiG4ShMg-oG5VW6DCtz5QTDfXzr02Y'); // Replace with your Google Sheet ID
  const sheet = ss.getSheetByName('NewInvoiceNumber');   // Replace with your sheet name
  
  
  const latestInvoiceNumber = sheet.getRange('B3').getValue();
  
  return ContentService.createTextOutput(latestInvoiceNumber);
}


function doPost(e) {
  try {
    const invoiceNumber = e.parameter.invoice_number;

    if (!invoiceNumber) {
      return ContentService.createTextOutput(JSON.stringify({ success: false, message: 'Missing invoice number.' }))
        .setMimeType(ContentService.MimeType.JSON);
    }

    // Get latest file from Accounts Master
    const masterSheetId = '1maVtj-WUt-9vlRiG4ShMg-oG5VW6DCtz5QTDfXzr02Y'; // Accounts Master ID
    const ss = SpreadsheetApp.openById(masterSheetId);
    const listSheet = ss.getSheetByName('ListofAccounts');

    // Read latest file details from row 2
    const latestFileId = listSheet.getRange('D2').getValue();
    const latestFileName = listSheet.getRange('A2').getValue();

    if (!latestFileId) {
      return ContentService.createTextOutput(JSON.stringify({ success: false, message: 'No latest file ID found in Accounts Master.' }))
        .setMimeType(ContentService.MimeType.JSON);
    }

    // Open the correct file
    const targetSpreadsheet = SpreadsheetApp.openById(latestFileId);
    const targetSheet = targetSpreadsheet.getSheetByName('Class Sales');

    if (!targetSheet) {
      return ContentService.createTextOutput(JSON.stringify({ success: false, message: 'Class Sales sheet not found in the latest spreadsheet.' }))
        .setMimeType(ContentService.MimeType.JSON);
    }

    const invoiceColumn = 3; // Column C

    // Find next empty row in Column C
    const invoiceData = targetSheet.getRange('C2:C' + targetSheet.getLastRow()).getValues().flat();
    let nextEmptyRow = invoiceData.findIndex(cell => !cell) + 2;

    if (nextEmptyRow < 2) {
      nextEmptyRow = targetSheet.getLastRow() + 1;
    }

    // Write the invoice number to Column C
    targetSheet.getRange(nextEmptyRow, invoiceColumn).setValue(invoiceNumber);

    //Write today's date to Column A (formatted as YYYY-MM-DD)
    const today = Utilities.formatDate(new Date(), Session.getScriptTimeZone(), "yyyy-MM-dd");
    targetSheet.getRange(nextEmptyRow, 1).setValue(today); // Column A = 1

    return ContentService.createTextOutput(JSON.stringify({
      success: true,
      message: 'Invoice number and date updated successfully.',
      updatedFile: latestFileName,
      updatedRow: nextEmptyRow,
      invoiceNumber: invoiceNumber,
      invoiceDate: today
    })).setMimeType(ContentService.MimeType.JSON);

  } catch (error) {
    return ContentService.createTextOutput(JSON.stringify({ success: false, message: error.message }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}





