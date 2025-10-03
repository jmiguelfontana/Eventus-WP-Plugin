(function($){
  $(function(){
    if(typeof baDataTables === 'undefined'){ return; }

    var form = $('form.eventus-search');
    if(!form.length){ return; }

    var input      = form.find('input[name="termino_busqueda"]');
    var searchBtn  = form.find('button[name="buscar_api"]');
    var resetBtn   = $('#evt_btnReset');
    var helpBtn   = $('#evt_btnHelp');
    // remember initial classes for these controls so we can ensure visual baseline after resets
    if (typeof searchBtn.data === 'function' && searchBtn.length && searchBtn.data('ba_initial_class') === undefined){ searchBtn.data('ba_initial_class', searchBtn.attr('class') || ''); }
    if (typeof resetBtn.data === 'function' && resetBtn.length && resetBtn.data('ba_initial_class') === undefined){ resetBtn.data('ba_initial_class', resetBtn.attr('class') || ''); }
    if (typeof helpBtn.data === 'function' && helpBtn.length && helpBtn.data('ba_initial_class') === undefined){ helpBtn.data('ba_initial_class', helpBtn.attr('class') || ''); }
    var spinnerMarkup = '<span class="ba-spinner" aria-hidden="true"></span>';
    var buttonLabels  = (baDataTables.i18n && baDataTables.i18n.buttons) || {};
    var excelFilename = buttonLabels.excelFilename || 'eventus-resultados';
    var excelTitle    = buttonLabels.excelTitle || 'Resultados de la busqueda';
    var excelText     = buttonLabels.excel || 'Exportar a Excel';
    var excelButtonHtml = '<span class="fa-solid fa-file-excel" aria-hidden="true"></span><span class="screen-reader-text">' + excelText + '</span>';

    // Helper: safe sessionStorage / JSON operations (centralize try/catch here)
    function safeGetItem(key){
      try{ return window.sessionStorage.getItem(key); } catch(e){ return null; }
    }
    function safeSetItem(key, value){
      try{ window.sessionStorage.setItem(key, value); return true; } catch(e){ return false; }
    }
    function safeRemoveItem(key){
      try{ window.sessionStorage.removeItem(key); return true; } catch(e){ return false; }
    }
    function safeParseJSON(s){
      if (!s) { return null; }
      try{ return JSON.parse(s); } catch(e){ return null; }
    }
    function safeStringify(v){
      try{ return JSON.stringify(v); } catch(e){ return null; }
    }

    // Find the most appropriate wrapper element for the DataTable (or use container)
    function findWrapperForApi(api){
      var wrap = container;
      if (api && typeof api.table === 'function'){
        var tblCont = api.table().container();
        if (tblCont) { wrap = $(tblCont); }
      } else if (typeof dataTable !== 'undefined' && dataTable && dataTable.table){
        var tblCont2 = dataTable.table().container();
        if (tblCont2) { wrap = $(tblCont2); }
      }
      return wrap;
    }

    function applyUiBusy(wrapper){
      searchBtn.prop('disabled', true).addClass('is-busy').attr('aria-busy', 'true');
      resetBtn.prop('disabled', true).addClass('is-busy').attr('aria-busy', 'true');
      helpBtn.prop('disabled', true).addClass('is-busy').attr('aria-busy', 'true');
      resetBtn.attr('aria-disabled','true').addClass('disabled');
      helpBtn.attr('aria-disabled','true').addClass('disabled');
      [searchBtn, resetBtn, helpBtn].forEach(function($b){
        if (typeof $b.data === 'function' && !$b.data('ba_original_class')){
          $b.data('ba_original_class', $b.attr('class') || '');
        }
        $b.addClass('button button-secondary');
      });
      if (wrapper && wrapper.find) {
        wrapper.find('.ba-export-excel, .dt-button').prop('disabled', true).addClass('is-busy').attr('aria-busy', 'true');
      }
      if (typeof dataTable !== 'undefined' && dataTable && dataTable.buttons) { dataTable.buttons().disable(); }
      if (wrapper && wrapper.css) {
        wrapper.css('position', 'relative');
        if (!wrapper.find('.ba-loading-overlay').length) {
          wrapper.append('<div class="ba-loading-overlay" style="position:absolute;top:0;left:0;width:100%;height:100%;z-index:2147483647;background:rgba(255,255,255,0.001);pointer-events:auto;"></div>');
        }
        wrapper.attr('data-ba-loading', 'true');
      }
      if(!searchBtn.find('.ba-spinner').length){
        searchBtn.find('.ba-btn-icon').hide();
        searchBtn.append(spinnerMarkup);
      }
    }

    function clearUiBusy(wrapper){
      searchBtn.prop('disabled', false).removeClass('is-busy').removeAttr('aria-busy');
      resetBtn.prop('disabled', false).removeClass('is-busy').removeAttr('aria-busy');
      helpBtn.prop('disabled', false).removeClass('is-busy').removeAttr('aria-busy');
      resetBtn.removeAttr('aria-disabled').removeClass('disabled');
      helpBtn.removeAttr('aria-disabled').removeClass('disabled');
      [searchBtn, resetBtn, helpBtn].forEach(function($b){
        if (typeof $b.data === 'function' && $b.data('ba_original_class') !== undefined){
          var orig = $b.data('ba_original_class') || '';
          $b.attr('class', orig);
          $b.removeData('ba_original_class');
        } else {
          $b.removeClass('button button-secondary');
        }
      });
      if (wrapper && wrapper.find) { wrapper.find('.ba-export-excel, .dt-button').prop('disabled', false).removeClass('is-busy').removeAttr('aria-busy'); }
      if (typeof dataTable !== 'undefined' && dataTable && dataTable.buttons) { dataTable.buttons().enable(); }
      if (wrapper && wrapper.find) { wrapper.find('.ba-loading-overlay').remove(); wrapper.removeAttr('data-ba-loading'); }
      searchBtn.find('.ba-spinner').remove();
      searchBtn.find('.ba-btn-icon').show();
      if (window.baDatatablesLoadingTimeout) { clearTimeout(window.baDatatablesLoadingTimeout); window.baDatatablesLoadingTimeout = null; }
      window.baDatatablesLoading = false;
      // Ensure the export button state reflects current table rows
      updateExportButtonState(typeof dataTable !== 'undefined' ? dataTable : null);
      // Ensure search and reset keep the baseline 'button' styling after restore
      [searchBtn, resetBtn].forEach(function($b){
        // prefer the originally stored baseline class if present
        if (typeof $b.data === 'function' && $b.data('ba_initial_class') !== undefined){
          var baseline = $b.data('ba_initial_class') || '';
          // ensure 'button' is present for consistent size/color
          if (baseline.indexOf('button') === -1) { $b.addClass('button'); }
          // do not clobber other classes; keep current class list but ensure baseline classes exist
          baseline.split(/\s+/).forEach(function(cls){ if (cls) { $b.addClass(cls); } });
        } else {
          if (!$b.hasClass('button')) { $b.addClass('button'); }
        }
      });
    }

    function setLoading(isLoading){
  window.baDatatablesLoading = !!isLoading;
  // clear any previous safety timeout
  if (window.baDatatablesLoadingTimeout) { clearTimeout(window.baDatatablesLoadingTimeout); window.baDatatablesLoadingTimeout = null; }
      var wrapper = findWrapperForApi(typeof dataTable !== 'undefined' ? dataTable : null);
      if(isLoading){
        // Do NOT change the OS mouse cursor. We block interaction using overlays and pointer-events only.
        applyUiBusy(wrapper);
        // safety: if loading remains for too long, automatically clear to avoid stuck UI
        window.baDatatablesLoadingTimeout = setTimeout(function(){
          if (window.baDatatablesLoading) {
            console.warn('baDataTables: loading timeout expired, clearing overlays');
            setLoading(false);
          }
        }, 15000); // 15 seconds
      } else {
        clearUiBusy(wrapper);
      }
    }

    var container = $('<div class="ba-resultados ba-resultados--datatables"></div>');
    form.after(container);

    var notice = $('<div class="ba-results-notice" aria-live="polite"></div>');
    var tableEl = $('<table class="ba-devices-table display" style="width:100%"></table>');
    // Asegurar que la tabla tenga un id estable para usar como clave en sessionStorage
    if (!tableEl.attr('id')) {
      var path = (window.location && window.location.pathname) ? (window.location.pathname || '').replace(/[^a-z0-9_-]/gi, '_') : '';
      var tableId = 'ba-devices-table' + (path ? '_' + path : '');
      tableEl.attr('id', tableId);
    }
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

  var tableId = tableEl.attr('id');
  var loadedState = null; // Will hold the parsed state loaded by stateLoadCallback
  // Leer filas guardadas antes de inicializar la tabla para pasarlas como `data`
  var initialRows = null;
  var savedInitial = safeGetItem('DataTablesData_' + tableId);
  if (savedInitial) { initialRows = safeParseJSON(savedInitial); } else { initialRows = null; }

  // Helper: enable/disable the Excel export button depending on whether table has data
  function updateExportButtonState(api){
    var count = (api && typeof api.rows === 'function') ? api.rows().count() : 0;
    var hasRows = Number(count) > 0;
    // Prefer Buttons API if available on this API instance
    if (api && typeof api.buttons === 'function'){
      if (hasRows) { api.buttons().enable(); }
      else { api.buttons().disable(); }
    }
    // DOM fallback: find buttons in wrapper and toggle disabled/disabled class
    var wrap = container;
    if (api && typeof api.table === 'function'){
      var tblCont = api.table().container();
      if (tblCont) { wrap = $(tblCont); }
    }
    var $btns = wrap.find('.ba-export-excel');
    $btns.each(function(){
      var $b = $(this);
      if (hasRows) {
        $b.prop('disabled', false).removeClass('disabled').removeAttr('aria-disabled');
      } else {
        $b.prop('disabled', true).addClass('disabled').attr('aria-disabled','true');
      }
    });
  }

  var dataTable = tableEl.DataTable({
    data: initialRows || [],
      dom: "<'ba-table-controls'lB>rtip",
      stateSave: true,
      // Use sessionStorage to persist DataTables built-in state (page, length, order, search)
      stateSaveCallback: function(settings, data) {
        safeSetItem('DataTablesState_' + tableId, safeStringify(data));
      },
      stateLoadCallback: function(settings) {
        var s = safeGetItem('DataTablesState_' + tableId);
        if (!s) { return null; }
        var parsed = safeParseJSON(s);
        if (!parsed) { return null; }
        // If we have a saved page index (from our custom save), merge it into DataTables' start
        var savedPage = safeGetItem('DataTablesPage_' + tableId);
        if (savedPage !== null && parsed && parsed.length) {
          var pageIndex = Number(savedPage) || 0;
          parsed.start = pageIndex * Number(parsed.length);
        }
        // keep a reference to use later in initComplete
        loadedState = parsed;
        return parsed;
      },
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
      //data: [],
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
            // Build a baseline class string using stored initial classes from controls (prefer searchBtn)
            var baseline = '';
            try{
              // Prefer reset/help buttons' baseline (they are simple 'button') to match toolbox controls
              if (typeof resetBtn.data === 'function' && resetBtn.data('ba_initial_class')) { baseline = resetBtn.data('ba_initial_class'); }
              else if (typeof helpBtn.data === 'function' && helpBtn.data('ba_initial_class')) { baseline = helpBtn.data('ba_initial_class'); }
              else if (typeof searchBtn.data === 'function' && searchBtn.data('ba_initial_class')) { baseline = searchBtn.data('ba_initial_class'); }
            } catch(e) { /* ignore */ }
            // Ensure the 'button' class exists for consistent sizing
            if (baseline.indexOf('button') === -1) { baseline = (baseline + ' button').trim(); }
            // Include a small icon span to match the other controls' internal structure
            var icon = '<span class="ba-btn-icon fa-solid fa-eye" aria-hidden="true"></span>';
            var sr = '<span class="screen-reader-text">Ver</span>';
            // Add tooltip (title) and accessible label
            var titleText = 'Ampliar información';
            return '<a class="' + baseline + ' ba-row-action" role="button" title="' + titleText + '" aria-label="' + titleText + '" href="/device/?id=' + (row.id || '') + '">' + sr + icon + '</a>';
          }
        }
      ],
      initComplete: function(){
        var api = this.api();
        var tableId = api.table().node().id;
        var $header = $(api.table().header());
        if ($header.find('tr.ba-filters-row').length) { return; }

        // Helper: construir la fila de filtros y adjuntar handlers
        function createFiltersRow(){
          var $filtersRow = $('<tr class="ba-filters-row"></tr>');
          api.columns().every(function(index){
            var column = api.column(index);
            var headerText = $(column.header()).text();
            var $cell = $('<th></th>');
            var columnSettings = column.settings()[0].aoColumns[index] || {};
            var isSearchable = !!columnSettings.bSearchable;
            if (!column.visible()){
              if (index === 0) { $cell.append('<span class="screen-reader-text">' + headerText + '</span>'); }
              $cell.css('display', 'none');
              $filtersRow.append($cell);
              return;
            }
            if (isSearchable){
              var $inputFilter = $('<input type="text" class="ba-col-filter" placeholder="' + headerText + '" />');
              $inputFilter.on('keyup change', function(){
                var val = $.trim(this.value);
                if (column.search() !== val) { column.search(val, false, false).draw(); }
                saveFilters();
              });
              $cell.append($inputFilter);
            } else { $cell.append('&nbsp;'); }
            $filtersRow.append($cell);
          });
          $header.append($filtersRow);
          return $filtersRow;
        }

        // Helper: guardar/recuperar filtros para una fila de filtros específica
        function saveFiltersForRow($filtersRow){
          var filters = {};
          api.columns().every(function(i){
            var $cell = $filtersRow.find('th').eq(i);
            var $inp = $cell.find('input.ba-col-filter');
            filters[i] = $inp.length ? $.trim($inp.val()) : '';
          });
          safeSetItem('DataTablesFilters_' + tableId, safeStringify(filters));
        }

        function restoreFilters($filtersRow){
          var savedFilters = safeGetItem('DataTablesFilters_' + tableId);
          if (savedFilters) {
            var filters = safeParseJSON(savedFilters);
            if (filters) {
              api.columns().every(function(i){
                var val = filters[i] || '';
                var column = api.column(i);
                var $cell = $filtersRow.find('th').eq(i);
                var $input = $cell.find('input.ba-col-filter');
                if ($input.length){ $input.val(val); if (column.search() !== val) { column.search(val, false, false); } }
              });
            }
          }
        }

        // Helper: restaurar búsqueda global
        function restoreGlobalSearch(){
          var savedTerm = safeGetItem('DataTablesSearchTerm_' + tableId);
          var globalSearch = '';
          if (savedTerm) { globalSearch = savedTerm; }
          else if (loadedState && loadedState.search) { globalSearch = loadedState.search.value || loadedState.search.search || ''; }
          if (!globalSearch) { return; }
          input.val(globalSearch);
          if (api.search() !== globalSearch) { api.search(globalSearch, false, false); }
        }

        // Helper: restaurar filas previamente guardadas (si no pasamos initialRows)
        function restoreRowsIfNeeded(){
          if (initialRows) { return; }
          var saved = safeGetItem('DataTablesData_' + tableId);
          if (!saved) { return; }
          var rows = safeParseJSON(saved);
          if (rows) { api.clear().rows.add(rows); }
        }

        // Helper: restaurar página objetivo (merge con DataTables state si aplica)
        function restorePage(){
          var desiredPage = null;
          if (loadedState && typeof loadedState.start !== 'undefined' && typeof loadedState.length !== 'undefined'){
            var len = Number(loadedState.length) || api.page.len();
            if (api.page.len() !== len) { api.page.len(len, false); }
            desiredPage = Math.floor(Number(loadedState.start) / len);
          } else {
            var savedPage = safeGetItem('DataTablesPage_' + tableId);
            if (savedPage !== null) { desiredPage = Number(savedPage); }
          }
          var rowsCount = api.rows().count();
          var pageLen = api.page.len();
          var maxPage = Math.max(0, Math.ceil(rowsCount / pageLen) - 1);
          if (desiredPage === null) { api.draw(false); }
          else { if (desiredPage > maxPage) { desiredPage = maxPage; } if (desiredPage < 0) { desiredPage = 0; } try{ api.page(desiredPage).draw(false); } catch(e){ api.draw(false); } }
        }

        // Helper: attach draw handler to save rows/page and update export button
        function attachDrawSave(){
      api.on('draw.dt', function(){
        var rowsData = api.rows().data().toArray();
        safeSetItem('DataTablesData_' + tableId, safeStringify(rowsData));
        safeSetItem('DataTablesPage_' + tableId, String(api.page()));
        updateExportButtonState(api);
      });
        }

  // Create filters row and bind saveFilters to the created row
  var $filtersRow = createFiltersRow();
  function saveFilters(){ saveFiltersForRow($filtersRow); }
        // restore filters/global search/rows/page and attach draw handler
        restoreFilters($filtersRow);
        restoreGlobalSearch();
        restoreRowsIfNeeded();
        restorePage();
        attachDrawSave();
        updateExportButtonState(api);
      }
    });

    // Fallback: re-aplicar la página guardada justo después de la inicialización (por si hubo race)
    setTimeout(function(){
      var savedPage = safeGetItem('DataTablesPage_' + tableId);
      if (savedPage !== null) { try{ dataTable.page(Number(savedPage)).draw(false); } catch(e) {} }
      // Ensure export button state is correct after initialization
      updateExportButtonState(dataTable);
    }, 50);

    // Cleanup overlays if page is unloaded or hidden (prevent stuck UI)
    var __baCleanup = function(){ if (window.baDatatablesLoading) { setLoading(false); } };
    window.addEventListener('beforeunload', __baCleanup, false);
    document.addEventListener('visibilitychange', function(){ if (document.visibilityState === 'hidden') { __baCleanup(); } }, false);

    // Reaplicar página cuando la página se muestra desde el bfcache (back/forward cache)
    window.addEventListener('pageshow', function(event){
      var savedPage = safeGetItem('DataTablesPage_' + tableId);
      if (savedPage !== null) { try{ dataTable.page(Number(savedPage)).draw(false); } catch(e) {} }
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
          // Ensure DataTables built-in filtering is cleared so server-provided rows are visible
          try {
            if (typeof dataTable !== 'undefined' && dataTable) {
              // clear global search and per-column searches
              try { dataTable.search(''); } catch(e) {}
              try { dataTable.columns().search(''); } catch(e) {}
            }
          } catch(e) { /* ignore */ }

          // Debug: report current global search and column searches before adding rows
          if (window.console && typeof window.console.debug === 'function') {
            try {
              var g = (dataTable && typeof dataTable.search === 'function') ? dataTable.search() : null;
              var cols = [];
              if (dataTable && dataTable.columns && typeof dataTable.columns === 'function'){
                dataTable.columns().every(function(i){ cols.push(dataTable.column(i).search()); });
              }
              console.debug('baDataTables: pre-add global search ->', g, 'column searches ->', cols);
            } catch(e) { console.debug('baDataTables: pre-add debug failed', e); }
          }

          dataTable.clear().rows.add(json.data).draw();
          // Debug: report how many rows were returned by server and how many are displayed after filtering
          if (window.console && typeof window.console.debug === 'function') {
            try {
              var returned = Array.isArray(json.data) ? json.data.length : 0;
              var displayed = (dataTable && dataTable.rows) ? dataTable.rows({ filter: 'applied' }).count() : 0;
              console.debug('baDataTables: server returned rows ->', returned, 'displayed after draw ->', displayed);
            } catch(e) { console.debug('baDataTables: post-add debug failed', e); }
          }
          // Guardar el término de búsqueda para restaurarlo al volver
          safeSetItem('DataTablesSearchTerm_' + tableId, String(term));
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

    // Guardar estado (página/término) justo antes de navegar a la página de device
    container.on('click', 'a.ba-row-action', function(){
      safeSetItem('DataTablesPage_' + tableId, String(dataTable.page()));
      safeSetItem('DataTablesSearchTerm_' + tableId, input.val() || '');
    });

    // Prevent clicks and key actions on controls while loading overlay is active
    // Use native listeners in capture phase so we intercept events before other handlers
    var __baPreventClick = function(e){ if (window.baDatatablesLoading) { e.stopImmediatePropagation(); e.preventDefault(); return false; } };
    document.addEventListener('click', __baPreventClick, true);
    // Also prevent Enter/Space keyboard activation while loading
    document.addEventListener('keydown', function(e){ if (window.baDatatablesLoading && (e.key === 'Enter' || e.key === ' ' || e.keyCode === 13 || e.keyCode === 32)) { e.stopImmediatePropagation(); e.preventDefault(); return false; } }, true);

    form.on('submit', function(evt){
      evt.preventDefault();

      // Read term from the expected input; if for some reason the named input selector
      // didn't locate the element, fall back to the #ba-term id which is present in markup.
      var term = '';
      try {
        term = $.trim((input && input.length) ? input.val() || '' : ($('#ba-term').length ? $('#ba-term').val() : ''));
      } catch(e){
        term = $.trim($('#ba-term').length ? $('#ba-term').val() : '');
      }
      // Debug aid: log the resolved search term (can be removed later)
      if (window.console && typeof window.console.debug === 'function') { console.debug('baDataTables: initiating search for term ->', term); }

      // User-initiated search should reset any per-column filters that could hide results
      try {
        // clear UI inputs
        tableEl.find('input.ba-col-filter').val('');
        // clear DataTables column searches
        if (typeof dataTable !== 'undefined' && dataTable && typeof dataTable.columns === 'function'){
          dataTable.columns().search('');
          // persist cleared filters state
          var id = (dataTable && dataTable.table && dataTable.table().node) ? dataTable.table().node().id : tableId;
          safeRemoveItem('DataTablesFilters_' + id);
        }
      } catch(e) { /* ignore */ }

      fetchData(term);
    });

    // Backup: if other scripts prevent normal form submit, also attach a click handler
    // to the search button to guarantee user-initiated searches call fetchData.
    if (searchBtn && searchBtn.length){
      searchBtn.on('click', function(evt){
        // allow the submit handler to run, but guard in case it's prevented elsewhere
        try {
          var fallbackTerm = '';
          try { fallbackTerm = $.trim((input && input.length) ? input.val() || '' : ($('#ba-term').length ? $('#ba-term').val() : '')); } catch(e){ fallbackTerm = $.trim($('#ba-term').length ? $('#ba-term').val() : ''); }
          if (!fallbackTerm) { return; }
          // prevent double-submit if the form is already going to handle it
          evt.preventDefault();
          // clear column filters as above
          try { tableEl.find('input.ba-col-filter').val(''); if (dataTable && dataTable.columns) { dataTable.columns().search(''); safeRemoveItem('DataTablesFilters_' + tableId); } } catch(e){}
          fetchData(fallbackTerm);
        } catch(e){ /* ignore */ }
      });
    }

    if(resetBtn.length){
      resetBtn.on('click', function(){
        setLoading(false);
        input.val('');
        dataTable.clear().draw();
        notice.empty();

        tableEl.find('input.ba-col-filter').val('');
        dataTable.columns().search('').draw();
        // Limpiar el estado guardado de filtros, filas, página y término
        var id = (dataTable && dataTable.table && dataTable.table().node) ? dataTable.table().node().id : tableId;
        safeRemoveItem('DataTablesFilters_' + id);
        safeRemoveItem('DataTablesData_' + id);
        safeRemoveItem('DataTablesPage_' + id);
        safeRemoveItem('DataTablesState_' + id);
        safeRemoveItem('DataTablesSearchTerm_' + id);
      });
    }

    // Si no hay filas iniciales pero hay un término guardado, re-ejecutar la búsqueda
    if (!initialRows) {
      var savedTerm = safeGetItem('DataTablesSearchTerm_' + tableId);
      if (savedTerm) {
        // pequeño retraso para asegurarnos de que DataTable esté lista
        setTimeout(function(){
          // sólo ejecutar si la tabla está vacía
          if (dataTable && typeof dataTable.rows === 'function' && dataTable.rows().count() === 0) {
            input.val(savedTerm);
            fetchData(savedTerm);
          }
        }, 50);
      }
    }
  });
})(jQuery);
