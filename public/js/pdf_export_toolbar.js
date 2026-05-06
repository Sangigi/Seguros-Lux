(function () {
    document.getElementById('btnPrintPdf')?.addEventListener('click', function () {
        window.print();
    });
    document.getElementById('btnClosePdf')?.addEventListener('click', function () {
        window.close();
    });
})();
