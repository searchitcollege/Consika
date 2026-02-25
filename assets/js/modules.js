/**
 * Module-specific JavaScript
 */

// ============================================
// ESTATE MODULE
// ============================================
const EstateModule = {
    init: function() {
        this.initPropertyMap();
        this.initTenantSearch();
        this.initPaymentCalculator();
        this.initMaintenanceTracker();
    },
    
    initPropertyMap: function() {
        if ($('#propertyMap').length) {
            // Initialize map for property locations
            let map = L.map('propertyMap').setView([-1.286389, 36.817223], 13);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            // Add markers for properties
            $('.property-location').each(function() {
                let lat = $(this).data('lat');
                let lng = $(this).data('lng');
                let name = $(this).data('name');
                
                if (lat && lng) {
                    L.marker([lat, lng]).addTo(map)
                        .bindPopup(name);
                }
            });
        }
    },
    
    initTenantSearch: function() {
        $('#tenantSearch').on('keyup', function() {
            let search = $(this).val().toLowerCase();
            
            $('.tenant-row').each(function() {
                let text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(search) > -1);
            });
        });
    },
    
    initPaymentCalculator: function() {
        $('#calculateRent').on('click', function() {
            let monthlyRent = parseFloat($('#monthlyRent').val()) || 0;
            let months = parseFloat($('#leaseMonths').val()) || 12;
            let deposit = parseFloat($('#deposit').val()) || 0;
            
            let totalRent = monthlyRent * months;
            let totalDue = totalRent + deposit;
            
            $('#totalRent').text(formatCurrency(totalRent));
            $('#totalDue').text(formatCurrency(totalDue));
        });
    },
    
    initMaintenanceTracker: function() {
        $('.mark-complete').on('click', function() {
            let maintenanceId = $(this).data('id');
            
            if (confirm('Mark this maintenance request as complete?')) {
                ajaxRequest(APP_URL + '/api/estate/maintenance-complete.php', 'POST', {
                    id: maintenanceId
                }, function() {
                    location.reload();
                });
            }
        });
    },
    
    getRentHistory: function(tenantId) {
        ajaxRequest(APP_URL + '/api/estate/rent-history.php', 'GET', {
            tenant_id: tenantId
        }, function(data) {
            let html = '';
            data.forEach(function(payment) {
                html += `
                    <tr>
                        <td>${formatDate(payment.payment_date)}</td>
                        <td>${formatCurrency(payment.amount)}</td>
                        <td>${payment.payment_method}</td>
                        <td><span class="badge bg-success">Paid</span></td>
                    </tr>
                `;
            });
            $('#rentHistory').html(html);
        });
    }
};

// ============================================
// PROCUREMENT MODULE
// ============================================
const ProcurementModule = {
    init: function() {
        this.initSupplierRating();
        this.initStockAlert();
        this.initPOGenerator();
        this.initInventoryTracker();
    },
    
    initSupplierRating: function() {
        $('.rating-input').rating({
            starCaptions: function(val) {
                return val + ' stars';
            }
        });
        
        $('.submit-rating').on('click', function() {
            let supplierId = $(this).data('supplier');
            let rating = $(this).closest('.rating-container').find('.rating-input').val();
            
            ajaxRequest(APP_URL + '/api/procurement/supplier-rating.php', 'POST', {
                supplier_id: supplierId,
                rating: rating
            }, function() {
                showToast('Rating submitted successfully', 'success');
            });
        });
    },
    
    initStockAlert: function() {
        $('.check-stock').on('click', function() {
            let productId = $(this).data('product');
            
            ajaxRequest(APP_URL + '/api/procurement/check-stock.php', 'GET', {
                product_id: productId
            }, function(data) {
                $('#stockLevel').text(data.current_stock);
                $('#reorderLevel').text(data.reorder_level);
                
                if (data.current_stock <= data.reorder_level) {
                    $('#stockAlert').removeClass('d-none');
                }
            });
        });
    },
    
    initPOGenerator: function() {
        $('#addPOItem').on('click', function() {
            let itemCount = $('.po-item').length + 1;
            let html = `
                <tr class="po-item">
                    <td>
                        <select class="form-control product-select" name="items[${itemCount}][product_id]" required>
                            <option value="">Select Product</option>
                        </select>
                    </td>
                    <td>
                        <input type="number" class="form-control quantity" name="items[${itemCount}][quantity]" required min="1">
                    </td>
                    <td>
                        <input type="number" class="form-control unit-price" name="items[${itemCount}][unit_price]" required min="0" step="0.01">
                    </td>
                    <td class="total-price">0.00</td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm remove-item">
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                </tr>
            `;
            $('#poItems').append(html);
            
            // Load products for select
            this.loadProducts($('.product-select:last'));
        }.bind(this));
        
        $(document).on('click', '.remove-item', function() {
            $(this).closest('tr').remove();
            this.calculatePOTotal();
        }.bind(this));
        
        $(document).on('change', '.quantity, .unit-price', function() {
            let row = $(this).closest('tr');
            let quantity = parseFloat(row.find('.quantity').val()) || 0;
            let price = parseFloat(row.find('.unit-price').val()) || 0;
            let total = quantity * price;
            
            row.find('.total-price').text(formatCurrency(total));
            this.calculatePOTotal();
        }.bind(this));
    },
    
    loadProducts: function(select) {
        ajaxRequest(APP_URL + '/api/procurement/products.php', 'GET', null, function(data) {
            let options = '<option value="">Select Product</option>';
            data.forEach(function(product) {
                options += `<option value="${product.id}" data-price="${product.price}">${product.name}</option>`;
            });
            select.html(options);
        });
    },
    
    calculatePOTotal: function() {
        let subtotal = 0;
        $('.po-item').each(function() {
            let totalText = $(this).find('.total-price').text();
            subtotal += parseFloat(totalText.replace('KES ', '').replace(',', '')) || 0;
        });
        
        let tax = subtotal * 0.16;
        let total = subtotal + tax;
        
        $('#poSubtotal').text(formatCurrency(subtotal));
        $('#poTax').text(formatCurrency(tax));
        $('#poTotal').text(formatCurrency(total));
    },
    
    initInventoryTracker: function() {
        $('#scanBarcode').on('click', function() {
            if (typeof Quagga !== 'undefined') {
                Quagga.init({
                    inputStream: {
                        name: "Live",
                        type: "LiveStream",
                        target: document.querySelector('#barcodeScanner')
                    },
                    decoder: {
                        readers: ["code_128_reader", "ean_reader", "upc_reader"]
                    }
                }, function(err) {
                    if (err) {
                        console.log(err);
                        return;
                    }
                    Quagga.start();
                });
                
                Quagga.onDetected(function(data) {
                    let code = data.codeResult.code;
                    $('#productBarcode').val(code);
                    Quagga.stop();
                    $('#barcodeScanner').hide();
                });
            }
        });
    }
};

// ============================================
// WORKS MODULE
// ============================================
const WorksModule = {
    init: function() {
        this.initProjectTimeline();
        this.initResourceAllocation();
        this.initProgressTracker();
        this.initDailyReports();
    },
    
    initProjectTimeline: function() {
        if ($('#projectTimeline').length) {
            let tasks = [];
            $('.gantt-task').each(function() {
                tasks.push({
                    id: $(this).data('id'),
                    name: $(this).data('name'),
                    start: $(this).data('start'),
                    end: $(this).data('end'),
                    progress: $(this).data('progress')
                });
            });
            
            let gantt = new Gantt("#projectTimeline", tasks, {
                view_mode: 'Month',
                language: 'en',
                on_click: function(task) {
                    window.location.href = APP_URL + '/modules/works/task.php?id=' + task.id;
                }
            });
        }
    },
    
    initResourceAllocation: function() {
        $('#allocateResource').on('click', function() {
            let projectId = $('#projectId').val();
            let employeeId = $('#employeeId').val();
            let hours = $('#hoursAllocated').val();
            
            ajaxRequest(APP_URL + '/api/works/allocate-resource.php', 'POST', {
                project_id: projectId,
                employee_id: employeeId,
                hours: hours
            }, function() {
                showToast('Resource allocated successfully', 'success');
                $('#resourceModal').modal('hide');
                location.reload();
            });
        });
    },
    
    initProgressTracker: function() {
        $('.update-progress').on('click', function() {
            let projectId = $(this).data('project');
            let progress = $('#progress_' + projectId).val();
            
            ajaxRequest(APP_URL + '/api/works/update-progress.php', 'POST', {
                project_id: projectId,
                progress: progress
            }, function() {
                $('.progress-bar[data-project="' + projectId + '"]')
                    .css('width', progress + '%')
                    .attr('aria-valuenow', progress)
                    .text(progress + '%');
                showToast('Progress updated', 'success');
            });
        });
    },
    
    initDailyReports: function() {
        $('#submitDailyReport').on('click', function() {
            let formData = new FormData($('#dailyReportForm')[0]);
            
            $.ajax({
                url: APP_URL + '/api/works/daily-report.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    showToast('Daily report submitted', 'success');
                    $('#dailyReportModal').modal('hide');
                }
            });
        });
    },
    
    calculateMaterialNeeds: function(projectId) {
        ajaxRequest(APP_URL + '/api/works/material-needs.php', 'GET', {
            project_id: projectId
        }, function(data) {
            let html = '';
            data.forEach(function(material) {
                html += `
                    <tr>
                        <td>${material.name}</td>
                        <td>${material.required}</td>
                        <td>${material.available}</td>
                        <td>${material.shortage}</td>
                    </tr>
                `;
            });
            $('#materialNeeds').html(html);
        });
    }
};

// ============================================
// BLOCK FACTORY MODULE
// ============================================
const BlockFactoryModule = {
    init: function() {
        this.initProductionTracker();
        this.initQualityControl();
        this.initSalesDashboard();
        this.initDeliveryTracker();
    },
    
    initProductionTracker: function() {
        $('#startProduction').on('click', function() {
            let productId = $('#productId').val();
            let quantity = $('#plannedQuantity').val();
            
            ajaxRequest(APP_URL + '/api/blockfactory/start-production.php', 'POST', {
                product_id: productId,
                quantity: quantity
            }, function(data) {
                $('#batchNumber').text(data.batch_number);
                $('#productionTimer').show();
                startProductionTimer();
            });
        });
        
        function startProductionTimer() {
            let startTime = Date.now();
            let timer = setInterval(function() {
                let elapsed = Math.floor((Date.now() - startTime) / 1000);
                let hours = Math.floor(elapsed / 3600);
                let minutes = Math.floor((elapsed % 3600) / 60);
                let seconds = elapsed % 60;
                
                $('#productionTime').text(
                    String(hours).padStart(2, '0') + ':' +
                    String(minutes).padStart(2, '0') + ':' +
                    String(seconds).padStart(2, '0')
                );
            }, 1000);
            
            $('#stopProduction').one('click', function() {
                clearInterval(timer);
                $('#productionTimer').hide();
                showToast('Production batch completed', 'success');
            });
        }
    },
    
    initQualityControl: function() {
        $('.record-defects').on('click', function() {
            let batchId = $(this).data('batch');
            let defects = $('#defects_' + batchId).val();
            
            ajaxRequest(APP_URL + '/api/blockfactory/record-defects.php', 'POST', {
                batch_id: batchId,
                defects: defects
            }, function() {
                showToast('Quality check recorded', 'success');
                location.reload();
            });
        });
        
        this.updateDefectChart();
    },
    
    updateDefectChart: function() {
        if ($('#defectChart').length) {
            ajaxRequest(APP_URL + '/api/blockfactory/defect-stats.php', 'GET', null, function(data) {
                new Chart(document.getElementById('defectChart'), {
                    type: 'pie',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            data: data.values,
                            backgroundColor: ['#4cc9f0', '#f72585', '#f8961e', '#4361ee']
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            });
        }
    },
    
    initSalesDashboard: function() {
        $('#quickSale').on('click', function() {
            let productId = $('#productId').val();
            let quantity = $('#saleQuantity').val();
            let customer = $('#customerName').val();
            
            ajaxRequest(APP_URL + '/api/blockfactory/quick-sale.php', 'POST', {
                product_id: productId,
                quantity: quantity,
                customer: customer
            }, function(data) {
                $('#invoiceNumber').text(data.invoice_number);
                $('#saleTotal').text(formatCurrency(data.total));
                $('#saleModal').modal('show');
            });
        });
        
        this.updateSalesChart();
    },
    
    updateSalesChart: function() {
        if ($('#salesChart').length) {
            ajaxRequest(APP_URL + '/api/blockfactory/sales-stats.php', 'GET', null, function(data) {
                new Chart(document.getElementById('salesChart'), {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Sales',
                            data: data.values,
                            borderColor: '#4361ee',
                            backgroundColor: 'rgba(67, 97, 238, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return 'KES ' + value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
            });
        }
    },
    
    initDeliveryTracker: function() {
        if ($('#deliveryMap').length) {
            let map = L.map('deliveryMap').setView([-1.286389, 36.817223], 13);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            // Track live deliveries
            this.trackDeliveries(map);
        }
    },
    
    trackDeliveries: function(map) {
        setInterval(function() {
            ajaxRequest(APP_URL + '/api/blockfactory/live-deliveries.php', 'GET', null, function(data) {
                // Clear existing markers
                map.eachLayer(function(layer) {
                    if (layer instanceof L.Marker) {
                        map.removeLayer(layer);
                    }
                });
                
                // Add new markers
                data.forEach(function(delivery) {
                    if (delivery.lat && delivery.lng) {
                        L.marker([delivery.lat, delivery.lng])
                            .addTo(map)
                            .bindPopup(`
                                <b>Delivery #${delivery.id}</b><br>
                                To: ${delivery.destination}<br>
                                Status: ${delivery.status}
                            `);
                    }
                });
            });
        }, 30000); // Update every 30 seconds
    },
    
    calculateRawMaterials: function(productId, quantity) {
        ajaxRequest(APP_URL + '/api/blockfactory/calculate-materials.php', 'GET', {
            product_id: productId,
            quantity: quantity
        }, function(data) {
            $('#cementNeeded').text(data.cement + ' kg');
            $('#sandNeeded').text(data.sand + ' kg');
            $('#aggregateNeeded').text(data.aggregate + ' kg');
            $('#waterNeeded').text(data.water + ' L');
            
            if (!data.sufficient) {
                $('#materialWarning').removeClass('d-none');
            }
        });
    }
};

// ============================================
// REPORTS MODULE
// ============================================
const ReportsModule = {
    init: function() {
        this.initReportGenerator();
        this.initScheduledReports();
        this.initDataExport();
    },
    
    initReportGenerator: function() {
        $('#generateReport').on('click', function() {
            let reportType = $('#reportType').val();
            let startDate = $('#startDate').val();
            let endDate = $('#endDate').val();
            let format = $('#reportFormat').val();
            
            window.location.href = APP_URL + '/reports/generate.php?type=' + reportType +
                '&start=' + startDate + '&end=' + endDate + '&format=' + format;
        });
        
        $('#previewReport').on('click', function() {
            let reportType = $('#reportType').val();
            let startDate = $('#startDate').val();
            let endDate = $('#endDate').val();
            
            ajaxRequest(APP_URL + '/api/reports/preview.php', 'POST', {
                type: reportType,
                start_date: startDate,
                end_date: endDate
            }, function(data) {
                $('#reportPreview').html(data.html);
                $('#previewModal').modal('show');
            });
        });
    },
    
    initScheduledReports: function() {
        $('#scheduleReport').on('click', function() {
            let reportName = $('#reportName').val();
            let reportType = $('#reportType').val();
            let frequency = $('#frequency').val();
            let recipients = $('#recipients').val();
            
            ajaxRequest(APP_URL + '/api/reports/schedule.php', 'POST', {
                name: reportName,
                type: reportType,
                frequency: frequency,
                recipients: recipients
            }, function() {
                showToast('Report scheduled successfully', 'success');
                $('#scheduleModal').modal('hide');
                location.reload();
            });
        });
    },
    
    initDataExport: function() {
        $('#exportData').on('click', function() {
            let exportType = $('#exportType').val();
            let dataType = $('#dataType').val();
            let dateRange = $('#dateRange').val();
            
            let url = APP_URL + '/api/export.php?type=' + exportType +
                '&data=' + dataType + '&range=' + dateRange;
            
            window.open(url, '_blank');
        });
    }
};

// ============================================
// INITIALIZE MODULES BASED ON CURRENT PAGE
// ============================================
$(document).ready(function() {
    if ($('body').hasClass('module-estate')) {
        EstateModule.init();
    }
    
    if ($('body').hasClass('module-procurement')) {
        ProcurementModule.init();
    }
    
    if ($('body').hasClass('module-works')) {
        WorksModule.init();
    }
    
    if ($('body').hasClass('module-blockfactory')) {
        BlockFactoryModule.init();
    }
    
    if ($('body').hasClass('page-reports')) {
        ReportsModule.init();
    }
});