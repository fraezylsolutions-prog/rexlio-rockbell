//pdf,print,export datatable
let jqry = $.noConflict();
jqry(document).ready(function () {
  "use strict";

  //use for every report view
  let today = new Date();
  let dd = today.getDate();
  let mm = today.getMonth() + 1; //January is 0!
  let yyyy = today.getFullYear();

  if (dd < 10) {
    dd = "0" + dd;
  }

  if (mm < 10) {
    mm = "0" + mm;
  }
  today = yyyy + "-" + mm + "-" + dd;

  //get title and datatable id name from hidden input filed that is before in the table in view page for every datatable
  let datatable_name = $(".datatable_name").attr("data-id_name");
  let title = $(".datatable_name").attr("data-title");
  let TITLE = title + "" +
    "" + today;
  /* Column visibility. Appended only when the colVis extension is actually loaded:
     this project ships two DataTables Buttons builds and colVis is present for only
     one of them, and naming an unregistered button makes Buttons throw, which aborts
     the whole table. Guarded, a view without the library simply keeps its old set. */
  let ir_colvis_btn = [];
  if (jqry.fn.dataTable && jqry.fn.dataTable.ext && jqry.fn.dataTable.ext.buttons && jqry.fn.dataTable.ext.buttons.colvis) {
    ir_colvis_btn = [{
      extend: "colvis",
      text: '<i class="fa-solid fa-table-columns"></i> Columns',
      titleAttr: "Column visibility",
    }];
  }
  jqry(`#${datatable_name},#datatable2`).DataTable({
    autoWidth: false,
    ordering: true,
    order: [[0, "desc"]],
    // dom: "Bfrtip",
    dom: '<"top-left-item col-sm-12 col-md-6"lf> <"top-right-item col-sm-12 col-md-6"B> t <"bottom-left-item col-sm-12 col-md-6 "i><"bottom-right-item col-sm-12 col-md-6 "p>',
    buttons: [
      {
        extend: "print",
        title: TITLE,
        text: '<i class="fa-solid fa-print"></i> Print',
        titleAttr: "print",
      },
      {
        extend: "copyHtml5",
        title: TITLE,
        text: '<i class="fa-solid fa-copy"></i> Copy',
        titleAttr: "Copy",
      },
      {
        extend: "excelHtml5",
        title: TITLE,
        text: '<i class="fa-solid fa-file-excel"></i> Excel',
        titleAttr: "Excel",
      },
      {
        extend: "csvHtml5",
        title: TITLE,
        text: '<i class="fa-solid fa-file-csv"></i> CSV',
        titleAttr: "CSV",
      },
      {
        extend: "pdfHtml5",
        title: TITLE,
        text: '<i class="fa-solid fa-file-pdf"></i> PDF',
        titleAttr: "PDF",
      },
    ].concat(ir_colvis_btn),
    language: {
      paginate: {
        previous: "Previous",
        next: "Next",
      },
    },
  });
});
