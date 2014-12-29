!function(t, e, i) {
	var o = ["webkit", "Moz", "ms", "O"], r = {}, n;

	function a(t, i) {
		var o = e.createElement(t || "div"), r;
		for(r in i) {
			o[r] = i[r];
		}
		return o
	}

	function s(t) {
		for(var e = 1, i = arguments.length; e < i; e++) {
			t.appendChild(arguments[e]);
		}
		return t
	}

	var f = function() {
		var t = a("style", {type: "text/css"});
		s(e.getElementsByTagName("head")[0], t);
		return t.sheet || t.styleSheet
	}();

	function l(t, e, i, o) {
		var a = [
			"opacity",
			e,
			~~(t * 100),
			i,
			o
		].join("-"), s = .01 + i / o * 100, l = Math.max(1 - (1 - t) / e * (100 - s), t), p = n.substring(0, n.indexOf("Animation")).toLowerCase(), u = p && "-" + p + "-" || "";
		if(!r[a]) {
			f.insertRule("@" + u + "keyframes " + a + "{" + "0%{opacity:" + l + "}" + s + "%{opacity:" + t + "}" + (s + .01) + "%{opacity:1}" + (s + e) % 100 + "%{opacity:" + t + "}" + "100%{opacity:" + l + "}" + "}", f.cssRules.length);
			r[a] = 1
		}
		return a
	}

	function p(t, e) {
		var r = t.style, n, a;
		if(r[e] !== i) {
			return e;
		}
		e = e.charAt(0).toUpperCase() + e.slice(1);
		for(a = 0; a < o.length; a++) {
			n = o[a] + e;
			if(r[n] !== i) {
				return n
			}
		}
	}

	function u(t, e) {
		for(var i in e) {
			t.style[p(t, i) || i] = e[i];
		}
		return t
	}

	function c(t) {
		for(var e = 1; e < arguments.length; e++) {
			var o = arguments[e];
			for(var r in o) {
				if(t[r] === i) {
					t[r] = o[r]
				}
			}
		}
		return t
	}

	function d(t) {
		var e = {x: t.offsetLeft, y: t.offsetTop};
		while(t = t.offsetParent) {
			e.x += t.offsetLeft, e.y += t.offsetTop;
		}
		return e
	}

	var h = {
		lines:     12,
		length:    7,
		width:     5,
		radius:    10,
		rotate:    0,
		corners:   1,
		color:     "#000",
		speed:     1,
		trail:     100,
		opacity:   1 / 4,
		fps:       20,
		zIndex:    2e9,
		className: "spinner",
		top:       "auto",
		left:      "auto",
		position:  "relative"
	};

	function m(t) {
		if(!this.spin) {
			return new m(t);
		}
		this.opts = c(t || {}, m.defaults, h)
	}

	m.defaults = {};
	c(m.prototype, {
		spin:       function(t) {
			this.stop();
			var e = this, i = e.opts, o = e.el = u(a(0, {className: i.className}), {position: i.position, width: 0, zIndex: i.zIndex}), r = i.radius + i.length + i.width, s, f;
			if(t) {
				t.insertBefore(o, t.firstChild || null);
				f = d(t);
				s = d(o);
				u(o, {
					left: (i.left == "auto" ? f.x - s.x + (t.offsetWidth >> 1) : parseInt(i.left, 10) + r) + "px",
					top:  (i.top == "auto" ? f.y - s.y + (t.offsetHeight >> 1) : parseInt(i.top, 10) + r) + "px"
				})
			}
			o.setAttribute("aria-role", "progressbar");
			e.lines(o, e.opts);
			if(!n) {
				var l = 0, p = i.fps, c = p / i.speed, h = (1 - i.opacity) / (c * i.trail / 100), m = c / i.lines;
				(function y() {
					l++;
					for(var t = i.lines; t; t--) {
						var r = Math.max(1 - (l + t * m) % c * h, i.opacity);
						e.opacity(o, i.lines - t, r, i)
					}
					e.timeout = e.el && setTimeout(y, ~~(1e3 / p))
				})()
			}
			return e
		}, stop:    function() {
			var t = this.el;
			if(t) {
				clearTimeout(this.timeout);
				if(t.parentNode) {
					t.parentNode.removeChild(t);
				}
				this.el = i
			}
			return this
		}, lines:   function(t, e) {
			var i = 0, o;

			function r(t, o) {
				return u(a(), {
					position:        "absolute",
					width:           e.length + e.width + "px",
					height:          e.width + "px",
					background:      t,
					boxShadow:       o,
					transformOrigin: "left",
					transform:       "rotate(" + ~~(360 / e.lines * i + e.rotate) + "deg) translate(" + e.radius + "px" + ",0)",
					borderRadius:    (e.corners * e.width >> 1) + "px"
				})
			}

			for(; i < e.lines; i++) {
				o = u(a(), {
					position:  "absolute",
					top:       1 + ~(e.width / 2) + "px",
					transform: e.hwaccel ? "translate3d(0,0,0)" : "",
					opacity:   e.opacity,
					animation: n && l(e.opacity, e.trail, i, e.lines) + " " + 1 / e.speed + "s linear infinite"
				});
				if(e.shadow) {
					s(o, u(r("#000", "0 0 4px " + "#000"), {top: 2 + "px"}));
				}
				s(t, s(o, r(e.color, "0 0 1px rgba(0,0,0,.1)")))
			}
			return t
		}, opacity: function(t, e, i) {
			if(e < t.childNodes.length) {
				t.childNodes[e].style.opacity = i
			}
		}
	});
	(function() {
		function t(t, e) {
			return a("<" + t + ' xmlns="urn:schemas-microsoft.com:vml" class="spin-vml">', e)
		}

		var e = u(a("group"), {behavior: "url(#default#VML)"});
		if(!p(e, "transform") && e.adj) {
			f.addRule(".spin-vml", "behavior:url(#default#VML)");
			m.prototype.lines = function(e, i) {
				var o = i.length + i.width, r = 2 * o;

				function n() {
					return u(t("group", {coordsize: r + " " + r, coordorigin: -o + " " + -o}), {width: r, height: r})
				}

				var a = -(i.width + i.length) * 2 + "px", f = u(n(), {position: "absolute", top: a, left: a}), l;

				function p(e, r, a) {
					s(f, s(u(n(), {rotation: 360 / i.lines * e + "deg", left: ~~r}), s(u(t("roundrect", {arcsize: i.corners}), {
						width: o, height: i.width, left: i.radius, top: -i.width >> 1, filter: a
					}), t("fill", {color: i.color, opacity: i.opacity}), t("stroke", {opacity: 0}))))
				}

				if(i.shadow) {
					for(l = 1; l <= i.lines; l++) {
						p(l, -2, "progid:DXImageTransform.Microsoft.Blur(pixelradius=2,makeshadow=1,shadowopacity=.3)");
					}
				}
				for(l = 1; l <= i.lines; l++) {
					p(l);
				}
				return s(e, f)
			};
			m.prototype.opacity = function(t, e, i, o) {
				var r = t.firstChild;
				o = o.shadow && o.lines || 0;
				if(r && e + o < r.childNodes.length) {
					r = r.childNodes[e + o];
					r = r && r.firstChild;
					r = r && r.firstChild;
					if(r) {
						r.opacity = i
					}
				}
			}
		} else {
			n = p(e, "animation")
		}
	})();
	if(typeof define == "function" && define.amd) {
		define(function() {
			return m
		});
	} else {
		t.Spinner = m
	}
}(window, document);

jQuery.fn.highlight = function(pat) {
	function innerHighlight(node, pat) {
		var skip = 0;
		if(node.nodeType == 3) {
			var pos = node.data.toUpperCase().indexOf(pat);
			if(pos >= 0) {
				var spannode = document.createElement('span');
				spannode.className = 'wpus-highlight';
				var middlebit = node.splitText(pos);
				var endbit = middlebit.splitText(pat.length);
				var middleclone = middlebit.cloneNode(true);
				spannode.appendChild(middleclone);
				middlebit.parentNode.replaceChild(spannode, middlebit);
				skip = 1;
			}
		} else if(node.nodeType == 1 && node.childNodes && !/(script|style)/i.test(node.tagName)) {
			for(var i = 0; i < node.childNodes.length; ++i) {
				i += innerHighlight(node.childNodes[i], pat);
			}
		}
		return skip;
	}

	return this.length && pat && pat.length ? this.each(function() {
		innerHighlight(this, pat.toUpperCase());
	}) : this;
};

jQuery.fn.removeHighlight = function() {
	return this.find("span.wpus-highlight").each(function() {
		this.parentNode.firstChild.nodeName;
		with(this.parentNode) {
			replaceChild(this.firstChild, this);
			normalize();
		}
	}).end();
};

jQuery(document).ready(function($) {

	var opts = {
		lines:     16, // The number of lines to draw
		length:    8, // The length of each line
		width:     3, // The line thickness
		radius:    10, // The radius of the inner circle
		corners:   1, // Corner roundness (0..1)
		rotate:    0, // The rotation offset
		color:     '#444', // #rgb or #rrggbb
		speed:     1.6, // Rounds per second
		trail:     60, // Afterglow percentage
		shadow:    false, // Whether to render a shadow
		hwaccel:   true, // Whether to use hardware acceleration
		className: 'spinner', // The CSS class to assign to the spinner
		zIndex:    2e9, // The z-index (defaults to 2000000000)
		top:       'auto', // Top position relative to parent in px
		left:      'auto' // Left position relative to parent in px
	};

	// Defaults for shortcode params
	var query = '';
	var include = '';
	var exclude = '';

	// Check to see if default parameters have been passed into the shortcode
	if(typeof shortcode_localize != 'undefined') {

		// Prepopulated query
		if('std' in shortcode_localize) {
			query = shortcode_localize.std;
		}

		// Include terms
		if('include' in shortcode_localize) {
			include = shortcode_localize.include;
		}

		// Exclude terms
		if('exclude' in shortcode_localize) {
			exclude = shortcode_localize.exclude;
		}

	}

	var visualSearch = VS.init({
		container: $("#search_box_container"), query: query, remainder: wpus_script.remainder, placeholder: wpus_script.placeholder, showFacets: wpus_script.showfacets, callbacks: {
			search:          function(query, searchCollection) {
				//		  	enable the following line for search query debugging:
				//			console.log(["query", searchCollection.facets(), query]);

				// Update routers
				var searchdata = [];
				var searchuri = 'search/';
				searchdata = searchCollection.facets();

				// Build the search URI
				for(var i = 0; i < searchdata.length; i++) {
					$.each(searchdata[i], function(k, v) {
						searchdata[i][k] = searchdata[i][k].replace('&', '\%and');
					});
					searchuri = searchuri + $.param(searchdata[i]);
					if(i < (searchdata.length - 1)) {
						searchuri = searchuri + "&";
					}
				}

				if(!query) {
					return;
				}

				// Set spinner target
				var spinner = new Spinner(opts).spin(document.getElementById('wpus_response'));

				// Dim results area while query is being conducted
				$("#wpus_response").animate({
					opacity: 0.5
				}, 500, function() {
				});

				var data = {
					action: "wpus_search", wpusquery: searchCollection.facets(), searchNonce: wpus_script.searchNonce
				};

				if($("#wpus_response").length > 0) {

					if(wpus_script.disable_permalinks != 1) {
						VS.app.searcher.navigate("/" + searchuri);
					}

					$.get(wpus_script.ajaxurl, data, function(response_from_get_results) {
						spinner.stop();
						$("#wpus_response").html(response_from_get_results);

						if(wpus_script.highlight) {
							for(var i = 0; i < searchdata.length; i++) {
								if(searchdata[i][wpus_script.remainder]) {
									var words = searchdata[i][wpus_script.remainder].split(' ');
									for(var word in words) {
										$("#wpus_response").highlight(words[word]);
									}
								}
							}
						}

						$("#wpus_response").animate({
							opacity: 1
						}, 500, function() {


							// Cancel / clear buttons
							$('#wpus-clear-search').click(function(e) {
								e.preventDefault();
								visualSearch.searchBox.clearSearch('type=keydown');
								$("#wpus_response").html("");
								VS.app.searcher.navigate("/");
							});
							$('.VS-icon-cancel').click(function(e) {
								$("#wpus_response").html("");
								VS.app.searcher.navigate("/");
							});

						});
						if(wpus_script.trackevents == true) {
							_gaq.push(['_trackEvent', wpus_script.eventtitle, 'Submit', searchCollection.serialize(), parseInt(wpus_response.numresults)]);
						}
					});
				} else {
					window.location.href = wpus_script.resultspage + "#" + searchuri;
				}
			}, valueMatches: function(category, searchTerm, callback) {

				if(wpus_script.radius && category == wpus_script.radius) {
					$('div.is_editing .search_facet_input_container input:not(.geocomplete)').addClass('geocomplete').geocomplete().bind("geocode:result", function(event, result) {
						query = visualSearch.searchBox.currentQuery;
						visualSearch.searchBox.value(query + ' "' + category + '": "' + $('.geocomplete').val() + '"');
						visualSearch.searchBox.searchEvent({});
					});
					return;
				}
				var data = {
					action: "wpus_getvalues", facet: category, include: include, exclude: exclude
				};
				$.get(wpus_script.ajaxurl, data, function(response_from_get_values) {
					if(response_from_get_values) {

						response_from_get_values = $.parseJSON(response_from_get_values);

						// Don't display a value if it's already in use in the search collection
						$.each(visualSearch.searchQuery.facets(), function(key, value) {
							$.each(value, function(key, value) {

								if(key == category) {
									response_from_get_values = $.grep(response_from_get_values, function(val) {
										return val != value;
									});
								}

							});
						});

						callback(response_from_get_values, {
							preserveOrder: true
						});
					}
				});
			}, facetMatches: function(callback) {
				var facets = $.parseJSON(wpus_script.enabledfacets.replace(/&quot;/g, '"'));
				var currentfacets = visualSearch.searchQuery.facets();

				// If only one facet has been enabled, and "single facet" mode is turned on
				if(facets.length == 1 && wpus_script.single_facet == true && currentfacets.length > 0) {

					var lastfacet = currentfacets[currentfacets.length - 1];

					if(lastfacet[Object.keys(lastfacet)[0]].length > 0) {

						// Add a regular facet to the box
						visualSearch.searchBox.addFacet(facets[0], '', 99);

					} else {

						// If the last facet in the box is empty, move the cursor to the previous one and open the autocomplete dropdown.
						visualSearch.searchBox.facetViews[currentfacets.length - 1].setCursorAtEnd(-1);
						visualSearch.searchBox.facetViews[currentfacets.length - 1].searchAutocomplete(-1);
					}

				} else if(facets.length == 1 && wpus_script.single_facet == true && currentfacets.length == 0) {

					// First facet being added to the box
					visualSearch.searchBox.addFacet(facets[0], '', 99);

				} else {
					// If we're only allowing each facet to be used once.
					if(wpus_script.single_use == 1) {
						var currentFacets = visualSearch.searchQuery.pluck("category");

						facets = facets.filter(function(el) {
							return currentFacets.indexOf(el) < 0;
						});
					}

					callback(facets, {
						preserveOrder: true
					});
				}
			}
		}
	});

	VS.utils.Searcher = Backbone.Router.extend({
		routes:    {
			"search/:query": "search"  // matches http://ultimatesearch.mindsharelabs.com/#search/query
		}, search: function(query) {

			if(!query) {
				return;
			}
			var result = new Array();

			query = query.replace(/\+/g, ' ');

			$.each(query.split('&'), function(index, value) {
				if(value) {
					value = value.replace('%and', '&');
					result.push(value);
				}
			});

			// Clear the search box
			visualSearch.searchBox.value('');

			// For each query parameter, break it into a key/value pair and create a facet
			$.each(result, function(index, value) {
				var param = value.split('=');
				visualSearch.searchBox.addFacet(param[0], param[1], 99);
			});

			// Execute the search
			visualSearch.searchBox.searchEvent({});

			// If we're in single facet mode, create a new facet and open the dropdown
			if(wpus_script.single_facet == true) {

				var facets = $.parseJSON(wpus_script.enabledfacets.replace(/&quot;/g, '"'));

				if(facets.length == 1) {
					visualSearch.searchBox.addFacet(facets[0], '', 99);
				}
			}

		}
	});
	// Initiate the router
	VS.app.searcher = new VS.utils.Searcher;

	// Start Backbone history a necessary step for bookmarkable URL's
	Backbone.history.start();

});
