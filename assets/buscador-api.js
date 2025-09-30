(function($){
  $(function(){
    if(typeof baDataTables === 'undefined'){ return; }

    var form = $('form.buscador-api');
    if(!form.length){ return; }

    var input      = form.find('input[name="termino_busqueda"]');
    var searchBtn  = form.find('button[name="buscar_api"]');
    var resetBtn   = $('#evt_btnReset');
    var spinnerMarkup = '<span class="ba-spinner" aria-hidden="true"></span>';
    var buttonLabels  = (baDataTables.i18n && baDataTables.i18n.buttons) || {};
    var excelFilename = buttonLabels.excelFilename || 'eventus-resultados';
    var excelTitle    = buttonLabels.excelTitle || 'Resultados de la busqueda';
    var excelText     = buttonLabels.excel || 'Exportar a Excel';
    var excelButtonHtml = '<span class="fa-solid fa-file-excel" aria-hidden="true"></span><span class="screen-reader-text">' + excelText + '</span>';

    function setLoading(isLoading){
      if(isLoading){
        searchBtn.prop('disabled', true).addClass('is-busy').attr('aria-busy', 'true');
        if(!searchBtn.find('.ba-spinner').length){
          searchBtn.append(spinnerMarkup);
        }
      } else {
        searchBtn.prop('disabled', false).removeClass('is-busy').removeAttr('aria-busy');
        searchBtn.find('.ba-spinner').remove();
      }
    }

    var container = $('<div class="ba-resultados ba-resultados--datatables"></div>');
    form.after(container);

    var notice = $('<div class="ba-results-notice" aria-live="polite"></div>');
    var tableEl = $('<table class="ba-devices-table display" style="width:100%"></table>');
    tableEl.append(
      '<thead><tr>'
        + '<th></th>'
        + '<th>'+ baDataTables.i18n.headers.name +'</th>'
        + '<th>'+ baDataTables.i18n.headers.primaryId +'</th>'
        + '<th>'+ baDataTables.i18n.headers.manufacturer +'</th>'
        + '<th>'+ baDataTables.i18n.headers.version +'</th>'
        + '<th>'+ baDataTables.i18n.headers.catalog +'</th>'
        + '<th> * </th>'
      + '</tr></thead><tbody></tbody>'
    );
    container.append(notice, tableEl);

    var dataTable = tableEl.DataTable({
      dom: "<'ba-table-controls'lB>rtip",
      buttons: [
        {
          extend: 'excelHtml5',
          className: 'button button-secondary ba-export-excel',
          text: excelButtonHtml,
          titleAttr: excelText,
          filename: excelFilename,
          title: excelTitle,
          exportOptions: {
            columns: [1, 2, 3, 4, 5]
          }
        }
      ],
      autoWidth: false,
      searching: true,
      data: [],
      language: baDataTables.language || {},
      order: [[1, 'asc']],
      columns: [
        { data: 'id', defaultContent: '', visible: false, searchable: false, orderable: false },
        { data: 'deviceName', defaultContent: '', visible: true, searchable: true, orderable: true },
        { data: 'primaryId', defaultContent: '', visible: true, searchable: true, orderable: true },
        { data: 'manufacturer', defaultContent: '', visible: true, searchable: true, orderable: true },
        { data: 'version', defaultContent: '', visible: true, searchable: true, orderable: true },
        { data: 'catalogNumber', defaultContent: '', visible: true, searchable: true, orderable: true },
        {
          data: null,
          orderable: false,
          searchable: false,
          className: 'dt-actions',
          title: 'Acciones',
          render: function(data, type, row){
            return '<button class="button ba-row-action" data-id="'+ (row.id || '') +'">Ver</button>';
          }
        }
      ],
      initComplete: function(){
        var api = this.api();
        var $header = $(api.table().header());
        if ($header.find('tr.ba-filters-row').length) {
          return;
        }
        var $filtersRow = $('<tr class="ba-filters-row"></tr>');
        api.columns().every(function(index){
          var column = api.column(index);
          var headerText = $(column.header()).text();
          var $cell = $('<th></th>');
          var columnSettings = column.settings()[0].aoColumns[index] || {};
          var isSearchable = !!columnSettings.bSearchable;

          if (!column.visible()) {
            if (index === 0) {
              $cell.append('<span class="screen-reader-text">' + headerText + '</span>');
            }
            $cell.css('display', 'none');
            $filtersRow.append($cell);
            return;
          }

          if (isSearchable) {
            var $inputFilter = $('<input type="text" class="ba-col-filter" placeholder="' + headerText + '" />');
            $inputFilter.on('keyup change', function(){
              var val = $.trim(this.value);
              if (column.search() !== val) {
                column.search(val, false, false).draw();
              }
            });
            $cell.append($inputFilter);
          } else {
            $cell.append('&nbsp;');
          }

          $filtersRow.append($cell);
        });
        $header.append($filtersRow);
      }
    });

    function fetchData(term){
      notice.empty();

      if(term === ''){
        setLoading(false);
        dataTable.clear().draw();
        notice.html('<div class="notice notice-warning"><p>'+ baDataTables.i18n.emptyTerm +'</p></div>');
        return;
      }

      setLoading(true);

      var request = $.ajax({
        url: baDataTables.ajax_url,
        method: 'POST',
        dataType: 'json',
        data: {
          action: 'ba_datatables_search',
          nonce: baDataTables.nonce,
          term: term
        }
      });

      request.done(function(json){
        notice.empty();
        if(json && json.error){
          dataTable.clear().draw();
          notice.html('<div class="notice notice-error"><p>'+ json.error +'</p></div>');
          return;
        }
        if(json && Array.isArray(json.data)){
          dataTable.clear().rows.add(json.data).draw();
        }
      });

      request.fail(function(){
        dataTable.clear().draw();
        notice.html('<div class="notice notice-error"><p>Se produjo un error al realizar la busqueda. Intentalo de nuevo.</p></div>');
      });

      request.always(function(){
        setLoading(false);
      });
    }

    form.on('submit', function(evt){
      evt.preventDefault();
      var term = $.trim(input.val() || '');
      fetchData(term);
    });

    if(resetBtn.length){
      resetBtn.on('click', function(){
        setLoading(false);
        input.val('');
        dataTable.clear().draw();
        notice.empty();

        tableEl.find('input.ba-col-filter').val('');
        dataTable.columns().search('').draw();
      });
    }
  });
})(jQuery);
