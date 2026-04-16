/**
 * SkyServers EAN Barcode Finder - Admin Script
 *
 * @package SkyServersEanBarcodeFinder
 * @since   1.0.0
 */

/* global jQuery, skyeanAjax */
(function( $ ) {
	'use strict';

	var i18n = skyeanAjax.i18n || {};

	var SKYEAN = {
		currentPage: 1,
		totalPages: 1,
		bulkRunning: false,
		bulkStopped: false,

		init: function() {
			this.loadProducts();
			this.bindEvents();
		},

		bindEvents: function() {
			$( '#skyean-filter-btn' ).on( 'click', function() {
				SKYEAN.currentPage = 1;
				SKYEAN.loadProducts();
			});

			$( '#skyean-sort-by, #skyean-per-page' ).on( 'change', function() {
				SKYEAN.currentPage = 1;
				SKYEAN.loadProducts();
			});

			$( '#skyean-search-name' ).on( 'keypress', function( e ) {
				if ( e.which === 13 ) {
					SKYEAN.currentPage = 1;
					SKYEAN.loadProducts();
				}
			});

			$( '#skyean-bulk-search-btn' ).on( 'click', function() {
				SKYEAN.startBulkSearch();
			});

			$( '#skyean-bulk-stop-btn' ).on( 'click', function() {
				SKYEAN.bulkStopped = true;
				$( this ).text( i18n.stopping );
			});

			$( document ).on( 'click', '.skyean-btn-search', function() {
				var $btn = $( this );
				SKYEAN.searchBarcode( $btn.data( 'id' ), $btn.data( 'name' ), $btn.closest( 'tr' ) );
			});

			$( document ).on( 'click', '.skyean-btn-save', function() {
				var $btn = $( this );
				var ean = $btn.closest( 'tr' ).find( '.skyean-ean-input' ).val().trim();
				SKYEAN.saveBarcode( $btn.data( 'id' ), ean, $btn.closest( 'tr' ) );
			});

			$( document ).on( 'click', '.skyean-btn-manual', function() {
				var $row = $( this ).closest( 'tr' );
				var productId = $( this ).data( 'id' );
				var $eanCell = $row.find( '.skyean-ean-cell' );
				$eanCell.html(
					'<input type="text" class="skyean-ean-input" placeholder="EAN-8 / UPC-12 / EAN-13" maxlength="13"> ' +
					'<button class="skyean-btn-save" data-id="' + productId + '">&#128190;</button>'
				);
				$eanCell.find( '.skyean-ean-input' ).focus();
			});

			$( document ).on( 'click', '.skyean-result-item', function() {
				SKYEAN.saveBarcode( $( this ).data( 'product-id' ), $( this ).data( 'ean' ) );
			});

			$( document ).on( 'click', '.skyean-page-btn:not(.disabled)', function() {
				var page = parseInt( $( this ).data( 'page' ), 10 );
				if ( page >= 1 && page <= SKYEAN.totalPages ) {
					SKYEAN.currentPage = page;
					SKYEAN.loadProducts();
					$( 'html, body' ).animate({ scrollTop: $( '#skyean-products-table' ).offset().top - 50 }, 200 );
				}
			});

			$( document ).on( 'keypress', '#skyean-jump-page', function( e ) {
				if ( e.which === 13 ) {
					var page = parseInt( $( this ).val(), 10 );
					if ( page >= 1 && page <= SKYEAN.totalPages ) {
						SKYEAN.currentPage = page;
						SKYEAN.loadProducts();
						$( 'html, body' ).animate({ scrollTop: $( '#skyean-products-table' ).offset().top - 50 }, 200 );
					}
				}
			});
		},

		getPerPage: function() {
			return parseInt( $( '#skyean-per-page' ).val(), 10 ) || 20;
		},

		loadProducts: function() {
			var $body = $( '#skyean-products-body' );
			$body.html( '<tr><td colspan="5" class="skyean-loading"><span class="skyean-spinner"></span> ' + i18n.loading + '</td></tr>' );

			var sortVal = $( '#skyean-sort-by' ).val() || 'title_asc';
			var parts = sortVal.split( '_' );
			var order = parts.pop();
			var orderby = parts.join( '_' );

			$.ajax({
				url: skyeanAjax.ajaxUrl,
				type: 'POST',
				timeout: 120000,
				data: {
					action: 'skyean_get_products',
					nonce: skyeanAjax.nonce,
					page: this.currentPage,
					per_page: this.getPerPage(),
					filter: $( '#skyean-filter-status' ).val(),
					category: $( '#skyean-filter-category' ).val(),
					search: $( '#skyean-search-name' ).val(),
					orderby: orderby,
					order: order.toUpperCase()
				},
				success: function( response ) {
					if ( ! response.success ) {
						$body.html( '<tr><td colspan="5" class="skyean-loading">' + i18n.loadError + '</td></tr>' );
						return;
					}

					var data = response.data;
					SKYEAN.totalPages = data.total_pages;

					if ( data.products.length === 0 ) {
						$body.html( '<tr><td colspan="5" class="skyean-loading">' + i18n.noProducts + '</td></tr>' );
						SKYEAN.renderPagination( 0 );
						return;
					}

					var html = '';
					$.each( data.products, function( idx, product ) {
						html += SKYEAN.renderProductRow( product );
					});

					$body.html( html );
					SKYEAN.renderPagination( data.total );
				},
				error: function( xhr, status ) {
					var msg = i18n.loadError;
					if ( status === 'timeout' ) {
						msg = i18n.timeoutError;
					} else if ( status === 'error' ) {
						msg = i18n.serverError;
					}
					$body.html(
						'<tr><td colspan="5" class="skyean-loading skyean-error-msg">' + msg +
						'<br><button class="button skyean-retry-btn" style="margin-top:10px;">' + i18n.retry + '</button></td></tr>'
					);
					$( '.skyean-retry-btn' ).on( 'click', function() {
						SKYEAN.loadProducts();
					});
				}
			});
		},

		renderProductRow: function( product ) {
			var eanHtml = product.ean
				? '<span class="skyean-ean-saved">' + SKYEAN.escHtml( product.ean ) + '</span>'
				: '<span style="color:#999">&mdash;</span>';

			return '<tr data-product-id="' + product.id + '">' +
				'<td><img src="' + SKYEAN.escHtml( product.thumb ) + '" class="skyean-thumb" alt=""></td>' +
				'<td><strong><a href="' + SKYEAN.escHtml( product.edit ) + '" target="_blank">' + SKYEAN.escHtml( product.name ) + '</a></strong></td>' +
				'<td><code>' + SKYEAN.escHtml( product.sku || '\u2014' ) + '</code></td>' +
				'<td><div class="skyean-ean-cell">' + eanHtml + '</div></td>' +
				'<td>' +
					'<button class="skyean-btn-search" data-id="' + product.id + '" data-name="' + SKYEAN.escAttr( product.name ) + '">&#128269; ' + i18n.searching.split( ':' )[0] + '</button> ' +
					'<button class="skyean-btn-manual" data-id="' + product.id + '">&#9998; Manual</button>' +
				'</td>' +
			'</tr>';
		},

		renderPagination: function( total ) {
			var $pag = $( '#skyean-pagination' );
			var tp = this.totalPages;
			var cp = this.currentPage;

			if ( tp <= 1 && total <= 0 ) {
				$pag.html( '' );
				return;
			}

			var perPage = this.getPerPage();
			var from = Math.min( ( ( cp - 1 ) * perPage ) + 1, total );
			var to = Math.min( cp * perPage, total );

			var html = '<div class="skyean-pag-row">';
			html += '<span class="skyean-pag-info">' + i18n.showing + ' ' + from + '&ndash;' + to + ' ' + i18n.of + ' <strong>' + total + '</strong> ' + i18n.products + '</span>';

			if ( tp > 1 ) {
				html += '<div class="skyean-pag-buttons">';
				html += '<button class="skyean-page-btn' + ( cp <= 1 ? ' disabled' : '' ) + '" data-page="1" title="' + i18n.firstPage + '">&laquo; ' + i18n.firstPage + '</button>';
				html += '<button class="skyean-page-btn' + ( cp <= 1 ? ' disabled' : '' ) + '" data-page="' + ( cp - 1 ) + '" title="' + i18n.prevPage + '">&lsaquo;</button>';

				var pages = this.getPageNumbers( cp, tp );
				for ( var idx = 0; idx < pages.length; idx++ ) {
					if ( pages[ idx ] === '...' ) {
						html += '<span class="skyean-pag-dots">&hellip;</span>';
					} else {
						html += '<button class="skyean-page-btn' + ( pages[ idx ] === cp ? ' active' : '' ) + '" data-page="' + pages[ idx ] + '">' + pages[ idx ] + '</button>';
					}
				}

				html += '<button class="skyean-page-btn' + ( cp >= tp ? ' disabled' : '' ) + '" data-page="' + ( cp + 1 ) + '" title="' + i18n.nextPage + '">&rsaquo;</button>';
				html += '<button class="skyean-page-btn' + ( cp >= tp ? ' disabled' : '' ) + '" data-page="' + tp + '" title="' + i18n.lastPage + '">' + i18n.lastPage + ' &raquo;</button>';
				html += '</div>';

				html += '<div class="skyean-pag-jump">';
				html += i18n.jumpTo + ' <input type="number" id="skyean-jump-page" min="1" max="' + tp + '" value="' + cp + '" class="skyean-jump-input"> / ' + tp;
				html += '</div>';
			}

			html += '</div>';
			$pag.html( html );
		},

		getPageNumbers: function( current, total ) {
			var i;
			if ( total <= 9 ) {
				var all = [];
				for ( i = 1; i <= total; i++ ) {
					all.push( i );
				}
				return all;
			}

			var pages = [ 1, 2 ];
			var rangeStart = Math.max( 3, current - 1 );
			var rangeEnd = Math.min( total - 2, current + 1 );

			if ( rangeStart > 3 ) {
				pages.push( '...' );
			}
			for ( i = rangeStart; i <= rangeEnd; i++ ) {
				pages.push( i );
			}
			if ( rangeEnd < total - 2 ) {
				pages.push( '...' );
			}

			pages.push( total - 1, total );
			return pages;
		},

		searchBarcode: function( productId, productName, $row ) {
			var $btn = $row.find( '.skyean-btn-search' );
			var originalText = $btn.html();
			$btn.html( '<span class="skyean-spinner"></span>' ).prop( 'disabled', true );
			$row.next( '.skyean-results-row' ).remove();

			$.post( skyeanAjax.ajaxUrl, {
				action: 'skyean_search_barcode',
				nonce: skyeanAjax.nonce,
				product_id: productId,
				product_name: productName
			}, function( response ) {
				$btn.html( originalText ).prop( 'disabled', false );

				if ( ! response.success ) {
					SKYEAN.addLog( i18n.searchError + ': ' + productName, 'error' );
					return;
				}

				var results = response.data.results;

				if ( results.length === 0 ) {
					SKYEAN.addLog( i18n.notFound + ': ' + productName, 'error' );
					var $noResults = $( '<tr class="skyean-results-row"><td colspan="5"><div class="skyean-no-results">' + i18n.noResults + '</div></td></tr>' );
					$row.after( $noResults );
					setTimeout( function() { $noResults.fadeOut( function() { $( this ).remove(); }); }, 4000 );
					return;
				}

				SKYEAN.addLog( results.length + ' result(s): ' + productName, 'success' );

				var html = '<tr class="skyean-results-row"><td colspan="5"><div>' +
					'<strong>' + i18n.resultsFor + ' "' + SKYEAN.escHtml( productName ) + '":</strong>' +
					'<ul class="skyean-results-list">';

				$.each( results, function( idx, r ) {
					var imgHtml = r.image ? '<img src="' + SKYEAN.escHtml( r.image ) + '" class="skyean-result-img" onerror="this.style.display=\'none\'">' : '';
					html += '<li class="skyean-result-item" data-ean="' + SKYEAN.escAttr( r.ean ) + '" data-product-id="' + productId + '">' +
						imgHtml +
						'<div class="skyean-result-info">' +
							'<div class="skyean-result-name">' + SKYEAN.escHtml( r.name ) + '</div>' +
							'<div class="skyean-result-meta">' + SKYEAN.escHtml( r.brand ) + ' &mdash; ' + SKYEAN.escHtml( r.source ) + '</div>' +
						'</div>' +
						'<span class="skyean-result-ean">' + SKYEAN.escHtml( r.ean ) + '</span>' +
					'</li>';
				});

				html += '</ul></div></td></tr>';
				$row.after( html );
			}).fail( function() {
				$btn.html( originalText ).prop( 'disabled', false );
				SKYEAN.addLog( i18n.networkError + ': ' + productName, 'error' );
			});
		},

		saveBarcode: function( productId, ean, $row ) {
			if ( ! $row ) {
				$row = $( 'tr[data-product-id="' + productId + '"]' );
			}

			$.post( skyeanAjax.ajaxUrl, {
				action: 'skyean_save_barcode',
				nonce: skyeanAjax.nonce,
				product_id: productId,
				ean: ean
			}, function( response ) {
				if ( ! response.success ) {
					SKYEAN.addLog( i18n.saveError + ': ' + response.data, 'error' );
					window.alert( response.data );
					return;
				}

				$row.find( '.skyean-ean-cell' ).html( '<span class="skyean-ean-saved">' + SKYEAN.escHtml( ean ) + '</span>' );
				$row.next( '.skyean-results-row' ).remove();
				SKYEAN.addLog( i18n.savedEan + ' ' + ean + ' ' + i18n.forProduct + ' #' + productId, 'success' );

				$row.css( 'background', '#e7f7e7' );
				setTimeout( function() { $row.css( 'background', '' ); }, 1500 );
			});
		},

		startBulkSearch: function() {
			if ( this.bulkRunning ) {
				return;
			}
			if ( ! window.confirm( i18n.bulkConfirm ) ) {
				return;
			}

			this.bulkRunning = true;
			this.bulkStopped = false;
			$( '#skyean-bulk-progress' ).show();
			$( '#skyean-bulk-search-btn' ).prop( 'disabled', true );
			this.addLog( i18n.bulkStarted, 'info' );
			this.bulkFetchAndProcess( 1 );
		},

		bulkFetchAndProcess: function( page ) {
			var self = this;
			$.post( skyeanAjax.ajaxUrl, {
				action: 'skyean_get_products',
				nonce: skyeanAjax.nonce,
				page: page,
				per_page: 20,
				filter: 'without_ean',
				category: '',
				search: ''
			}, function( response ) {
				if ( ! response.success || response.data.products.length === 0 ) {
					self.bulkFinish();
					return;
				}
				$( '#skyean-progress-total' ).text( response.data.total );
				self.bulkProcessQueue( response.data.products, 0, page, response.data.total_pages, 0, 0, 0 );
			});
		},

		bulkProcessQueue: function( products, index, currentPage, totalPages, processed, found, notFoundCount ) {
			var self = this;

			if ( self.bulkStopped ) {
				self.addLog( i18n.bulkStopped, 'info' );
				self.bulkFinish();
				return;
			}

			if ( index >= products.length ) {
				if ( found > 0 || currentPage < totalPages ) {
					setTimeout( function() { self.bulkFetchAndProcess( 1 ); }, 1000 );
				} else {
					self.bulkFinish();
				}
				return;
			}

			var product = products[ index ];
			processed++;

			var progress = Math.round( ( processed / parseInt( $( '#skyean-progress-total' ).text(), 10 ) ) * 100 );
			$( '.skyean-progress-fill' ).css( 'width', Math.min( progress, 100 ) + '%' );
			$( '#skyean-progress-current' ).text( processed );
			self.addLog( i18n.searching + ': ' + product.name, 'info' );

			$.post( skyeanAjax.ajaxUrl, {
				action: 'skyean_search_barcode',
				nonce: skyeanAjax.nonce,
				product_id: product.id,
				product_name: product.name
			}, function( response ) {
				var next = function() {
					setTimeout( function() {
						self.bulkProcessQueue( products, index + 1, currentPage, totalPages, processed, found, notFoundCount );
					}, 2000 );
				};

				if ( response.success && response.data.results.length === 1 ) {
					var ean = response.data.results[0].ean;
					$.post( skyeanAjax.ajaxUrl, {
						action: 'skyean_save_barcode',
						nonce: skyeanAjax.nonce,
						product_id: product.id,
						ean: ean
					}, function() {
						found++;
						$( '#skyean-progress-found' ).text( found + ' ' + i18n.found );
						self.addLog( '\u2705 ' + product.name + ' \u2192 ' + ean, 'success' );
						next();
					});
				} else if ( response.success && response.data.results.length > 1 ) {
					notFoundCount++;
					$( '#skyean-progress-notfound' ).text( notFoundCount + ' ' + i18n.toReview );
					self.addLog( '\u26A0\uFE0F ' + product.name + ' \u2192 ' + response.data.results.length + ' ' + i18n.manualCheck, 'error' );
					next();
				} else {
					notFoundCount++;
					$( '#skyean-progress-notfound' ).text( notFoundCount + ' ' + i18n.notFound );
					self.addLog( '\u274C ' + product.name + ' \u2192 ' + i18n.notFound, 'error' );
					next();
				}
			}).fail( function() {
				notFoundCount++;
				self.addLog( '\u274C ' + i18n.networkError + ': ' + product.name, 'error' );
				setTimeout( function() {
					self.bulkProcessQueue( products, index + 1, currentPage, totalPages, processed, found, notFoundCount );
				}, 3000 );
			});
		},

		bulkFinish: function() {
			this.bulkRunning = false;
			this.bulkStopped = false;
			$( '#skyean-bulk-search-btn' ).prop( 'disabled', false );
			$( '#skyean-bulk-stop-btn' ).text( i18n.stop );
			this.addLog( i18n.bulkFinished, 'info' );
			this.loadProducts();
		},

		addLog: function( message, type ) {
			var $log = $( '#skyean-log' );
			var time = new Date().toLocaleTimeString();
			$log.prepend( '<div class="log-entry log-' + ( type || 'info' ) + '">[' + time + '] ' + message + '</div>' );
		},

		escHtml: function( str ) {
			if ( ! str ) {
				return '';
			}
			var div = document.createElement( 'div' );
			div.appendChild( document.createTextNode( str ) );
			return div.innerHTML;
		},

		escAttr: function( str ) {
			if ( ! str ) {
				return '';
			}
			return str.replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
		}
	};

	$( document ).ready( function() {
		if ( $( '#skyean-products-table' ).length ) {
			SKYEAN.init();
		}
	});

}( jQuery ));
