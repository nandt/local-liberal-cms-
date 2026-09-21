(function ($, Drupal, drupalSettings) {
  const current_nid = $(".liberal-node-loader[data-current]").attr(
    "data-current"
  );

  // get the parameters of the url
  const params = new Proxy(new URLSearchParams(window.location.search), {
    get: (searchParams, prop) => searchParams.get(prop),
  });

  const liberalPreloadSection = function (section = "all") {
    var options = {
      type: "POST",
      dataType: "json",
      contentType: "application/json",
      data: JSON.stringify({ current_nid: current_nid }),
      url:
        drupalSettings.liberal_node_loader.base_path +
        "/api/node-loader/preload/get/" + section
    };

    // refresh to load nids
    options.success = function (response) {};

    // populate the ad
    options.error = function (response) {};

    return $.ajax(options);
  };

  Drupal.liberalNodeLoader = Drupal.liberalNodeLoader || {};
  var loadNid;

  Drupal.liberalNodeLoader.currentRegion = "all";

  Drupal.liberalNodeLoader.ArticleInfo = [];
  Drupal.liberalNodeLoader.toLoadNids = [];
  Drupal.liberalNodeLoader.markets = false;
  var preload = false;

  const browserWidth = window.innerWidth;
  newsfeedInject = $(".short-feed.news-feed");

  // original nid title and url
  // preserve ".gr" on original article title
  Drupal.liberalNodeLoader.ArticleInfo[current_nid] = {
    nodeUrl: drupalSettings.liberal_node_loader.currentNode.nodeUrl,
    nodeTitle: `${drupalSettings.liberal_node_loader.currentNode.nodeTitle}.gr`,
    nodeAds: drupalSettings.liberal_node_loader.currentNode.nodeAds,
  };

  window.dataLayer = window.dataLayer || [];
  if (window.dataLayer) {
    window.dataLayer.push(function () {
      this.set(
        "page_path",
        Drupal.liberalNodeLoader.ArticleInfo[current_nid].nodeUrl
      );
    });

    // Send title and page view to google analytics
    window.dataLayer.push({
      event: "page_view",
      pageTitle: Drupal.liberalNodeLoader.ArticleInfo[current_nid].nodeTitle,
    });
  }

  // change this number if you want the node to load faster before reaching bottom
  const scrollThreshold = 2000;
  var noScrolling = false;
  var scrollHeight = $(document).height();
  var currentscrollHeight = 0;
  pos = [];

  // initialize the map of articles
  const liberalArticleMap = function (selector) {
    pos = $(selector)
      .map(function () {
        var $this = $(this);
        return { el: $this, top: $this.offset().top };
      })
      .get();
  };

  const appendWidgetNews = function () {
    if (newsfeedInject.length > 0) {
      $(".inject-news").before(newsfeedInject.clone()).remove();
    }
  };

  $(window).scroll(function () {
    scrollHeight = $(document).height();
    const scrollPos = Math.floor($(window).height() + $(window).scrollTop());
    const isBottom = scrollHeight - scrollThreshold < scrollPos;

    // Process loading the next node.
    if (!noScrolling && isBottom && currentscrollHeight < scrollHeight) {
      noScrolling = true;

      // load batch
      if (!preload) {
        liberalPreloadSection(Drupal.liberalNodeLoader.currentRegion).then(
          function (data, textStatus) {
            // don't run preload again
            preload = true;

            // remove current node id from the list before loading
            const indexNid = data.toLoadNids.indexOf(parseInt(current_nid));
            if (indexNid > -1) {
              data.toLoadNids.splice(indexNid, 1);
            }

            Drupal.liberalNodeLoader.toLoadNids = data.toLoadNids;
            loadNid = Drupal.liberalNodeLoader.toLoadNids.shift();

            if (typeof loadNid !== "undefined") {
              liberalLoadHomeArticle(loadNid);
            }
          }
        );
      } else {
        loadNid = Drupal.liberalNodeLoader.toLoadNids.shift();

        if (typeof loadNid !== "undefined") {
          liberalLoadHomeArticle(loadNid);
        }
      }
    }
  });

  $(window).resize(function () {
    $(window).scroll();
  });

  /*
   * Scroll function to deal with url change
   * we need to update each time the user scrolls to a particular threshhold
   */

  $(window).scroll(function () {
    // add a class to know which article the user is viewing currently
    $(".article-start").removeClass("liberal-node-loader-inview");
    var scroll = $(this).scrollTop();
    var i = 0;
    while (typeof pos[i] != "undefined" && pos[i].top < scroll) {
      i++;
    }
    i--;

    if (typeof pos[i] != "undefined") {
      pos[i].el.addClass("liberal-node-loader-inview");

      var nid = $(".article-start.liberal-node-loader-inview").attr(
        "data-history-node-id"
      );

      if (typeof Drupal.liberalNodeLoader.ArticleInfo[nid] != "undefined") {
        if (
          Drupal.liberalNodeLoader.ArticleInfo[nid].nodeUrl !=
          window.location.pathname
        ) {
          // Url
          let themeSwitching = params.view_theme;
          let nodeUrl;

          if (themeSwitching == "liberal_stocks_mobile") {
            nodeUrl =
              Drupal.liberalNodeLoader.ArticleInfo[nid].nodeUrl +
              "?view_theme=" +
              themeSwitching;
          } else {
            nodeUrl = Drupal.liberalNodeLoader.ArticleInfo[nid].nodeUrl;
          }

          history.replaceState(null, "", nodeUrl);
          // Change the document's title.
          document.title = Drupal.liberalNodeLoader.ArticleInfo[nid].nodeTitle;

          // update page views only once
          if (
            typeof Drupal.liberalNodeLoader.ArticleInfo[nid].dataLayer ==
              "undefined" &&
            i != 0
          ) {
            Drupal.liberalNodeLoader.ArticleInfo[nid].dataLayer = true;

            if (window.dataLayer) {
              window.dataLayer.push(function () {
                this.set(
                  "page_path",
                  Drupal.liberalNodeLoader.ArticleInfo[nid].nodeUrl
                );
              });

              // Send title and page view to google analytics
              window.dataLayer.push({
                event: "page_view",
                pageTitle: Drupal.liberalNodeLoader.ArticleInfo[nid].nodeTitle,
              });
            }
          }
        }
      }
    }
  });

  const moreAds = function (nid, targetData = {}) {
    var ajxNode = [
      {
        sizes: [
          [970, 250],
          [728, 90],
        ],
        div: "div-gpt-ad-5305849-ajx-" + nid + "-1",
        slot: "DR8-970x250",
        tags: ["desktop", "laptop"],
        collapse: true,
        refresh: true,
      },
      {
        sizes: [[300, 600]],
        div: "div-gpt-ad-5305849-ajx-" + nid + "-2",
        slot: "DR4-300x600",
        tags: ["desktop", "laptop"],
        refresh: true,
      },
      {
        sizes: [[300, 250]],
        div: "div-gpt-ad-5305849-ajx-" + nid + "-3",
        slot: "DR6-300x250",
        tags: ["desktop", "laptop"],
        refresh: true,
      },
      {
        sizes: [[300, 250]],
        div: "div-gpt-ad-5305849-ajx-" + nid + "-6",
        slot: "DL1-300x600",
        tags: ["desktop"],
        refresh: true,
      },
      {
        sizes: [[300, 250]],
        div: "div-gpt-ad-5305849-ajx-" + nid + "-7",
        slot: "DL2-300x600",
        tags: ["desktop"],
        refresh: true,
      },
    ];

    for (var i = 0; i < ajxNode.length; i++) {
      // var defined in html.html.twig
      if (ajxNode[i].tags.includes(selectedBreakpointLabel)) {
        displayAd(ajxNode[i], targetData);
      }
    }
  };

  const displayAd = function (adSlot, targetData = {}) {
    var definedSlot = googletag.defineSlot(
      "/21772425/" + adSlot.slot,
      adSlot.sizes,
      adSlot.div
    );

    // if a collapse setting is set for this slot, use it
    if (adSlot.collapse) {
      definedSlot.setCollapseEmptyDiv(adSlot.collapse);
    }

    /** Lazy loading configuration
     *   Custom targeting for lazy loading
     */
    definedSlot.setTargeting("node_loader", "lazyload");

    for (const [key, value] of Object.entries(targetData)) {
      definedSlot.setTargeting(key, value);
    }

    definedSlot.addService(googletag.pubads());

    if (typeof googletag.pubads().enableLazyLoad !== "undefined") {
      googletag.pubads().enableLazyLoad({
        // Fetch slots within 1 viewport.
        fetchMarginPercent: 100,
        // Render slots upon reaching the viewport.
        renderMarginPercent: 0,
        // Previous value multiplier for mobile
        mobileScaling: 1.0
      });
    }

    if (adSlot.refresh) {
      definedSlot.setTargeting(REFRESH_KEY, REFRESH_VALUE);
    }

    //register and fetch an ad.
    googletag.cmd.push(function () {
      googletag.display(adSlot.div);
    });
  };

  const liberalLoadHomeArticle = function (nid) {
    // send data
    if ($(".app-wrapper").hasClass("liberal-markets")) {
      Drupal.liberalNodeLoader.markets = true;
    }

    let themeSwitching = params.view_theme;

    if (themeSwitching == "liberal_stocks_mobile") {
      callbackUrl =
        drupalSettings.liberal_node_loader.base_path +
        "/api/node-loader/get/" +
        nid +
        "?view_theme=" +
        themeSwitching;
    } else {
      callbackUrl =
        drupalSettings.liberal_node_loader.base_path +
        "/api/node-loader/get/" +
        nid;
    }

    var options = {
      type: "POST",
      dataType: "json",
      contentType: "application/json",
      data: JSON.stringify({ markets: Drupal.liberalNodeLoader.markets }),
      url: callbackUrl,
    };

    options.success = function (response) {
      $(".liberal-node-loader").before(response.html);

    // make a request to count the impression
    if (response.readMoreNids.nids) {
      $.ajax({
        type: "POST",
        cache: false,
        url: drupalSettings.lat_impressions.url,
        data: response.readMoreNids
      });
    }

    // make a request to count the page view
    if (response.loadedNid) {
      setTimeout(() => {
        $.ajax({
          type: 'POST',
          cache: false,
          url: drupalSettings.statistics.url,
          data: { 'nid': response.loadedNid }
        });
      });
    }

      // save this info into an array so we can manipulate the urls, titles
      Drupal.liberalNodeLoader.ArticleInfo[nid] = {
        nodeUrl: response.nodeUrl,
        nodeTitle: response.nodeTitle,
        nodeAds: response.nodeAds,
      };

      // run the ads only if article has ads
      if (!Drupal.liberalNodeLoader.ArticleInfo[nid].nodeAds) {
        let targetDataJson = JSON.parse(response.targetData);

        if (browserWidth < 768) {
          // populate mobile ads
          loadMobileArticleAds(loadNid, "fromNodeLoader", targetDataJson);
        } else {
          // populate the ads
          moreAds(loadNid, targetDataJson);
        }
      }

      appendWidgetNews();

      // update the scroll height again, we added elements
      currentscrollHeight = scrollHeight;

      if (Drupal.liberalNodeLoader.toLoadNids.length <= 0) {
        noScrolling = true;
      } else {
        noScrolling = false;
      }

      // update map of articles
      liberalArticleMap(".article-start");

      // attach the promo click counter
      Drupal.behaviors.recordPromotedClicks.attach(
        $(".liberal-node-loader").prev(".app-wrapper .read-more-articles-loader").get(0),
        Drupal.settings
      );

      // Attach only the facebookMessengerShare behavior again
      Drupal.behaviors.facebookMessengerShare.attach(
        $(".liberal-node-loader").prev(".app-wrapper").get(0),
        Drupal.settings
      );
    };
    options.error = function (response) {
      noScrolling = true;
    };

    $.ajax(options);
  };
})(jQuery, Drupal, drupalSettings);
