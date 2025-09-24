(function($){
  $(function(){
    if(typeof baDataTables === 'undefined'){ return; }

    var form = $('form.buscador-api');
    if(!form.length){ return; }

    var input   = form.find('input[name="termino_busqueda"]');
    var searchBtn = form.find('button[name="buscar_api"]');

    // Añadimos el botón Reset
    var resetBtn = $('<button type="button" class="button">Reset</button>');
    searchBtn.after(resetBtn);

    // Contenedor resultados
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
      + '</tr></thead><tbody></tbody>'
    );
    container.append(notice, tableEl);

    var dataTable = tableEl.DataTable({
      dom: 'lrtip',
      autoWidth: false,
      searching: true,
      data: [],
      language: baDataTables.language || {},
      order: [[1, 'asc']],
      columns: [        
        { data: 'id', defaultContent: '', visible: false },
        { data: 'deviceName', defaultContent: '' },
        { data: 'primaryId', defaultContent: '' },
        { data: 'manufacturer', defaultContent: '' },
        { data: 'version', defaultContent: '' },
        { data: 'catalogNumber', defaultContent: '' }
      ],
      initComplete: function(){
        var api = this.api();
        var $header = $(api.table().header());
        if ($header.find('tr.ba-filters-row').length) {
          return;
        }
        var $filtersRow = $('<tr class="ba-filters-row"></tr>');
        api.columns().every(function(){
          var column = this;
          var headerText = $(column.header()).text();
          var $cell = $('<th></th>');

          if (column.visible()) {
            var $cell = $('<th></th>');
            if (column.index() === 0) {
              $cell.append('<span class="screen-reader-text">' + headerText + '</span>');
            } else {
              var $input = $('<input type="text" class="ba-col-filter" placeholder="' + headerText + '" />');
              $input.on('keyup change', function(){
                var val = $.trim(this.value);
                if (column.search() !== val) {
                  column.search(val, false, false).draw();
                }
              });
              $cell.append($input);
            }
            $filtersRow.append($cell);
          }
        });
        $header.append($filtersRow);
      }
    });

    function fetchData(term){
      notice.empty();

      if(term === ''){
        dataTable.clear().draw();
        notice.html('<div class="notice notice-warning"><p>'+ baDataTables.i18n.emptyTerm +'</p></div>');
        return;
      }

      $.post(baDataTables.ajax_url, {
        action: 'ba_datatables_search',
        nonce: baDataTables.nonce,
        term: term
      }, function(json){
        if(json && json.error){
          dataTable.clear().draw();
          notice.html('<div class="notice notice-error"><p>'+ json.error +'</p></div>');
          return;
        }
        if(json && Array.isArray(json.data)){
          dataTable.clear().rows.add(json.data).draw();
          if(json.data.length === 0){
            notice.html('<div class="notice notice-info"><p>'+ baDataTables.i18n.noResults +'</p></div>');
          }
        }
      }, 'json');
    }

    // Buscar
    form.on('submit', function(evt){
      evt.preventDefault();
      var term = $.trim(input.val() || '');
      fetchData(term);
    });

    // Reset
    resetBtn.on('click', function(){
      input.val('');
      dataTable.clear().draw();
      notice.empty();

      // limpiar filtros de columna también
      tableEl.find('input.ba-col-filter').val('');
      dataTable.columns().search('').draw();
    });
  });
})(jQuery);
