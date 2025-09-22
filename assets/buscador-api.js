(function($){
  function ensureContainer(form){
    var container = form.nextAll('.ba-resultados').first();
    if(!container.length){
      container = $('<div class="ba-resultados"></div>');
      form.after(container);
    } else {
      container.empty();
    }
    container.addClass('ba-resultados--datatables');
    return container;
  }

  function createTableMarkup(container){
    var notice = $('<div class="ba-results-notice" aria-live="polite"></div>');
    var table = $('<table class="ba-devices-table display" style="width:100%"></table>');
    var thead = $('<thead><tr><th></th><th>' + baDataTables.i18n.headers.name + '</th><th>' + baDataTables.i18n.headers.primaryId + '</th><th>' + baDataTables.i18n.headers.manufacturer + '</th><th>' + baDataTables.i18n.headers.version + '</th><th>' + baDataTables.i18n.headers.catalog + '</th></tr></thead>');
    table.append(thead).append('<tbody></tbody>');
    container.append(notice, table);
    return { notice: notice, table: table };
  }

  function setButtonBusy(btn, busy){
    if(!btn.length){ return; }
    if(busy){
      btn.addClass("is-busy").attr("aria-busy","true").prop("disabled", true);
      if(!btn.find(".ba-spinner").length){
        btn.append('<span class="ba-spinner" aria-hidden="true"></span>');
      }
    } else {
      btn.removeClass("is-busy").attr("aria-busy","false").prop("disabled", false);
      btn.find(".ba-spinner").remove();
    }
  }
  function stripBom(text){
    if(!text){
      return '';
    }
    return String(text).replace(/^\uFEFF/, '');
  }

  function formatUnexpectedMessage(prefix, responseText){
    if(!responseText){
      return prefix;
    }
    var trimmed = stripBom(responseText).replace(/^[\s\u200B]+|[\s\u200B]+$/g, '');
    if(trimmed.length > 180){
      trimmed = trimmed.slice(0, 177) + 'Ã¢â‚¬Â¦';
    }
    if(trimmed === '0'){
      return prefix + ' (WordPress devolviÃƒÂ³ 0; acciÃƒÂ³n Ajax inexistente o sin cargar)';
    }
    return prefix + ' -> ' + trimmed;
  }

  function normaliseRows(json){
    if(!json){
      return [];
    }
    var data = json.data;
    if(typeof data === 'string'){
      try {
        data = JSON.parse(stripBom(data));
      } catch(e){
        console.error('No se pudo parsear json.data', e, data);
        return [];
      }
    }
    if(Array.isArray(data)){
      return data;
    }
    if(data && typeof data === 'object'){
      var maybeArray = Object.keys(data).map(function(key){ return data[key]; });
      return maybeArray;
    }
    return [];
  }

  $(function(){
    if(typeof baDataTables === 'undefined'){ return; }
    var form = $('form.buscador-api');
    if(!form.length){ return; }

    var button = form.find('button[name="buscar_api"]');
    var container = ensureContainer(form);
    var markup = createTableMarkup(container);
    var tableEl = markup.table;
    var notice = markup.notice;
    var currentTerm = '';

    var dataTable = tableEl.DataTable({
      dom: 'lrtip',
      autoWidth: false,
      processing: false,
      serverSide: false,
      searching: true,
      data: [],
      language: baDataTables.language || {},
      order: [[1, 'asc']],
      columns: [
        { data: null, orderable: false, className: 'dt-control', defaultContent: '', width: '28px' },
        { data: 'display_name', defaultContent: '' },
        { data: 'main_id', defaultContent: '' },
        { data: 'manufacturer', defaultContent: '' },
        { data: 'version_model', defaultContent: '' },
        { data: 'catalog_number', defaultContent: '' }
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
        });
        $header.append($filtersRow);
      }
    });

    tableEl.on('click', 'td.dt-control', function(){
      var tr = $(this).closest('tr');
      var row = dataTable.row(tr);
      if(row.child.isShown()){
        row.child.hide();
        tr.removeClass('is-expanded');
      } else {
        var data = row.data() || {};
        var html = data.details_html ? data.details_html : '<em>' + baDataTables.i18n.noDetails + '</em>';
        row.child('<div class="ba-dt-details">' + html + '</div>').show();
        tr.addClass('is-expanded');
      }
    });

    function fetchData(){
      notice.empty();

      if(currentTerm === ''){
        setButtonBusy(button, false);
        dataTable.clear().draw();
        notice.html('<div class="notice notice-warning"><p>' + baDataTables.i18n.emptyTerm + '</p></div>');
        return;
      }

      $.ajax({
        url: baDataTables.ajax_url,
        type: 'POST',
        dataType: 'text',
        data: {
          action: 'ba_datatables_search',
          nonce: baDataTables.nonce,
          term: currentTerm
        }
      }).done(function(responseText){
        var cleanText = stripBom(responseText);
        var json;
        try {
          json = cleanText ? JSON.parse(cleanText) : null;
        } catch(parseError){
          console.error('Respuesta Ajax no es JSON vÃƒÂ¡lido', parseError, responseText);
          dataTable.clear().draw();
          notice.html('<div class="notice notice-error"><p>' + formatUnexpectedMessage(baDataTables.i18n.unexpected, responseText) + '</p></div>');
          return;
        }

        if(json && json.error){
          dataTable.clear().draw();
          notice.html('<div class="notice notice-error"><p>' + json.error + '</p></div>');
          return;
        }

        var rows = normaliseRows(json);
        if(Array.isArray(rows)){
          dataTable.clear().rows.add(rows).draw();
          if(rows.length === 0){
            notice.html('<div class="notice notice-info"><p>' + baDataTables.i18n.noResults + '</p></div>');
          }
          return;
        }

        console.error('json.data no es un array utilizable', json);
        dataTable.clear().draw();
        notice.html('<div class="notice notice-error"><p>' + formatUnexpectedMessage(baDataTables.i18n.unexpected, responseText) + '</p></div>');
      }).fail(function(xhr){
        var message = baDataTables.i18n.unexpected + ' (' + xhr.status + ')';
        if(xhr && xhr.responseJSON && xhr.responseJSON.error){
          message = xhr.responseJSON.error;
        } else if(xhr && xhr.responseText){
          message = formatUnexpectedMessage(message, xhr.responseText);
        }
        dataTable.clear().draw();
        notice.html('<div class="notice notice-error"><p>' + message + '</p></div>');
      }).always(function(){
        setButtonBusy(button, false);
      });
    }

    form.on('submit', function(evt){
      evt.preventDefault();
      currentTerm = $.trim(form.find('input[name="termino_busqueda"]').val() || '');
      setButtonBusy(button, true);
      fetchData();
    });
  });
})(jQuery);




