BX.ready(function () {
	console.log("[SCRIPT] BX готов");

    // 1. Ждём инициализации карточки сделки
	BX.addCustomEvent("BX.Crm.EntityEditor:onInit", function (editor) {
		console.log("[SCRIPT] onInit стартовал");
		var dealId = editor._entityId;
		if (!dealId) return;
		window.__dealId = dealId;

        // Получаем адреса и ранее сохранённый map
		BX.ajax({
			url: '/local/ajax/get_company_addresses.php',
			method: 'POST',
			dataType: 'json',
			data: { dealId: dealId },
			onsuccess: function(res) {
				if (res.error) {
					console.error(res.error);
					return;
				}
				window.__addresses  = res.addresses || [];
				window.__addressMap = res.map       || {};
				console.log("[SCRIPT] Адреса и map получены", window.__addressMap);
			},
			onfailure: function() {
				console.error("AJAX не удался");
			}
		});
	});

    // 2. Следим за обновлением грида
	BX.addCustomEvent("BX.Main.grid:updated", function (grid) {
		if (grid && grid.getId && grid.getId() === 'CCrmEntityProductListComponent') {
			addHeader();             
			renderDropdownsIfReady();
		}
	});

    // 3. MutationObserver на появление таблицы
	var observer = new MutationObserver(function(mutations, obs) {
		var table = document.getElementById('CCrmEntityProductListComponent_table');
		if (table) {
			addHeader();
			renderDropdownsIfReady();
		}
	});
	observer.observe(document.body, { childList: true, subtree: true });

    // 4. Запасной таймаут
	setTimeout(function() {
		addHeader();
		renderDropdownsIfReady();
	}, 2000);
});

function addHeader() {
	var headerRow = document.querySelector(
		'#CCrmEntityProductListComponent_table thead.main-grid-header tr.main-grid-row-head'
		);
	if (!headerRow) return;

    // 1) добавляем колонку «Дата», если ещё не добавлена
	if (!headerRow.querySelector('.custom-date-header')) {
		var thDate = document.createElement('th');
		thDate.className = 'main-grid-cell-head main-grid-cell-right main-grid-col-no-sortable custom-date-header';
		var innerDate = document.createElement('div');
		innerDate.className = 'main-grid-cell-inner';
		var spanDate = document.createElement('span');
		spanDate.className = 'main-grid-cell-head-container main-grid-head-title';
		spanDate.textContent = 'Дата';
		innerDate.appendChild(spanDate);
		thDate.appendChild(innerDate);
		headerRow.appendChild(thDate);
	}

    // 2) добавляем колонку «Адрес объекта», если ещё не добавлена
	if (!headerRow.querySelector('.custom-address-header')) {
		var thAddr = document.createElement('th');
		thAddr.className = 'main-grid-cell-head main-grid-cell-right main-grid-col-no-sortable custom-address-header';
		var innerAddr = document.createElement('div');
		innerAddr.className = 'main-grid-cell-inner';
		var spanAddr = document.createElement('span');
		spanAddr.className = 'main-grid-cell-head-container main-grid-head-title';
		spanAddr.textContent = 'Адрес объекта';
		innerAddr.appendChild(spanAddr);
		thAddr.appendChild(innerAddr);
		headerRow.appendChild(thAddr);
	}
}

function renderDropdownsIfReady() {
	var addresses = window.__addresses || [];
	if (!addresses.length) {
		console.warn("[SCRIPT] Нет адресов — не рендерим");
		return;
	}
	var table = document.getElementById('CCrmEntityProductListComponent_table');
	if (!table) {
		console.warn("[SCRIPT] Таблица ещё не подгружена");
		return;
	}
	console.log("[SCRIPT] Рендерим dropdown-ы");
	addAddressDropdowns(addresses);
}

function addAddressDropdowns(addresses) {
	var table = document.getElementById('CCrmEntityProductListComponent_table');
	if (!table) return;

	var rows = table.querySelectorAll('tbody tr.main-grid-row-body.main-grid-row-edit');
	rows.forEach(function (row, idx) {
        // 1) колонка «Дата»
		var existingDate = row.querySelector('.custom-date-cell');
		if (!existingDate) {
			var dateCell = row.insertCell(-1);
			dateCell.className = 'main-grid-cell main-grid-cell-right custom-date-cell';
			dateCell.setAttribute('data-editable', 'true');
			dateCell.innerHTML = '\
			<div class="main-grid-editor-container ui-entity-editor-content-block">\
			<span class="fields date field-wrap">\
			<span class="fields date field-item">\
			<input onclick="BX.calendar({node:this,field:this,bTime:false,bSetFocus:false})" \
			name="" type="text" tabindex="0" value="">\
			<i class="fields date icon" onclick="BX.calendar({\
				node:this.previousElementSibling, \
				field:this.previousElementSibling, \
				bTime:false, \
				bSetFocus:false\
				})"></i>\
				</span>\
				</span>\
			</div>';
		}

        // 2) колонка «Адрес объекта»
		var existingAddr = row.querySelector('.custom-address-cell');
		if (existingAddr) {
            // обновить значение, если оно есть в map
			var sel = existingAddr.querySelector('select');
			var saved = (window.__addressMap || {})[idx] || '';
			if (sel) sel.value = saved;
			return;
		}

		var cell = row.insertCell(-1);
		cell.className = 'main-grid-cell main-grid-cell-right custom-address-cell';
		cell.setAttribute('data-editable', 'true');
		cell.style.pointerEvents = 'auto';

		var editorContainer = document.createElement('div');
		editorContainer.className = 'main-grid-editor-container';

		var editorWrapper = document.createElement('div');
		editorWrapper.className = 'main-grid-editor main-grid-editor-select';

		var uiWrapper = document.createElement('div');
		uiWrapper.className = 'ui-ctl ui-ctl-after-icon ui-ctl-w100';

		var select = document.createElement('select');
		select.className = 'ui-ctl-element';
		select.style.pointerEvents = 'auto';
		select.style.zIndex = '1000';

		select.addEventListener('mousedown', function(e){ e.stopPropagation(); });
		select.addEventListener('click',     function(e){ e.stopPropagation(); });
		select.addEventListener('change',    function(){
			saveAddress(idx, select.value);
		});

        // пункт по умолчанию
		var defaultOpt = document.createElement('option');
		defaultOpt.value = '';
		defaultOpt.textContent = 'Не выбрано';
		select.appendChild(defaultOpt);

		addresses.forEach(function(addr){
			var opt = document.createElement('option');
			opt.value = addr;
			opt.textContent = addr;
			select.appendChild(opt);
		});

        // восстановить сохранённое значение
		var saved = (window.__addressMap || {})[idx] || '';
		if (saved) select.value = saved;

		var arrow = document.createElement('div');
		arrow.className = 'ui-ctl-after ui-ctl-icon-angle-down';
		arrow.style.pointerEvents = 'none';

		uiWrapper.appendChild(select);
		uiWrapper.appendChild(arrow);
		editorWrapper.appendChild(uiWrapper);
		editorContainer.appendChild(editorWrapper);
		cell.appendChild(editorContainer);
	});
}


function saveAddress(rowIndex, addressValue) {
	var dealId = window.__dealId;
	console.log("[SCRIPT] Сохраняем адрес для row", rowIndex, ":", addressValue);

	BX.ajax.runComponentAction('custom:deal.address', 'saveAddress', {
		mode: 'class',
		data: {
			dealId: dealId,
			rowIndex: rowIndex,
			address: addressValue
		}
	}).then(function (res) {
		console.log("[SCRIPT] Ответ saveAddress:", res);
        // сразу обновляем маппинг, чтобы он был актуален при следующем рендере
		window.__addressMap = window.__addressMap || {};
		window.__addressMap[rowIndex] = addressValue;
	}).catch(function (err) {
		console.error("[SCRIPT] Ошибка saveAddress:", err);
	});
}

