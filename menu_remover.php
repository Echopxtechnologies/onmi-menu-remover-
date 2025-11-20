<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Menu Remover
Description: Remove specific menu items (Order list, Shipments, Files, Calendar) from client area navigation + Customize Omni Sales + Auto Redirect Root URL & Login
Version: 1.7
Author: EchoPx
*/

define('MENU_REMOVER_MODULE_NAME', 'menu_remover');

// ================================================================
// MODULE ACTIVATION / DEACTIVATION HOOKS
// ================================================================

register_activation_hook(MENU_REMOVER_MODULE_NAME, 'menu_remover_activation_hook');
register_deactivation_hook(MENU_REMOVER_MODULE_NAME, 'menu_remover_deactivation_hook');

function menu_remover_activation_hook()
{
    log_activity('Menu Remover: Module activated');
}

function menu_remover_deactivation_hook()
{
    log_activity('Menu Remover: Module deactivated');
}

// ================================================================
// AUTO-REDIRECT ROOT URL & CLIENT HOMEPAGE TO OMNI SALES
// ================================================================

/**
 * Redirect when exact root URL is accessed: https://automation.erpblr.in/
 * Also redirect /clients homepage to Omni Sales
 * 
 * @hook app_init
 * @priority 1 (execute early)
 */
hooks()->add_action('app_init', 'menu_remover_root_url_redirect', 1);

function menu_remover_root_url_redirect()
{
    $CI = &get_instance();
    
    // Get current URI segments
    $segment1 = $CI->uri->segment(1);
    $segment2 = $CI->uri->segment(2);
    $segment3 = $CI->uri->segment(3);
    
    // Get the complete URI string
    $uri_string = $CI->uri->uri_string();
    
    // Check if user is logged in as client
    $is_client_logged_in = is_client_logged_in();
    
    // ============================================
    // CASE 1: Root URL - https://automation.erpblr.in/
    // ============================================
    if (empty($uri_string) || $uri_string === '/' || $uri_string === '') {
        
        // If client is logged in, redirect to Omni Sales
        if ($is_client_logged_in) {
            log_activity('Menu Remover: Redirecting from root URL to Omni Sales');
            redirect('omni_sales/omni_sales_client/index/1/4/0');
            exit();
        }
        // If not logged in, let them see the homepage/login
        return;
    }
    
    // ============================================
    // CASE 2: Client Portal Homepage - /clients or /clients/index
    // ============================================
    $is_client_homepage = (
        $segment1 === 'clients' && 
        (
            empty($segment2) || 
            ($segment2 === 'index' && empty($segment3))
        )
    );
    
    if ($is_client_homepage && $is_client_logged_in) {
        log_activity('Menu Remover: Redirecting from client homepage to Omni Sales');
        redirect('omni_sales/omni_sales_client/index/1/4/0');
        exit();
    }
}

/**
 * Redirect after successful client login to Omni Sales
 * 
 * @hook after_client_login
 */
hooks()->add_action('after_client_login', 'menu_remover_redirect_after_login');

function menu_remover_redirect_after_login()
{
    $CI = &get_instance();
    
    // Log the redirect
    log_activity('Menu Remover: Redirecting client after login to Omni Sales');
    
    // Set session variable to force redirect (in case Perfex tries to override)
    $CI->session->set_userdata('omni_sales_login_redirect', true);
    
    // Construct the Omni Sales URL with correct parameters
    $omni_sales_url = site_url('omni_sales/omni_sales_client/index/1/4/0');
    
    // Perform the redirect
    redirect($omni_sales_url);
    exit();
}

/**
 * Additional hook to ensure redirect happens even if after_client_login doesn't work
 * This catches the redirect right after authentication
 * 
 * @hook after_client_area_init
 * @priority 1
 */
hooks()->add_action('after_client_area_init', 'menu_remover_force_omni_sales_redirect', 1);

function menu_remover_force_omni_sales_redirect()
{
    $CI = &get_instance();
    
    // Check if we just logged in (session variable set by after_client_login)
    if ($CI->session->userdata('omni_sales_login_redirect')) {
        
        // Clear the session variable
        $CI->session->unset_userdata('omni_sales_login_redirect');
        
        // Get current URI
        $current_uri = $CI->uri->uri_string();
        
        // If we're not already on Omni Sales, redirect
        if (strpos($current_uri, 'omni_sales') === false) {
            log_activity('Menu Remover: Force redirecting to Omni Sales after login');
            redirect('omni_sales/omni_sales_client/index/1/4/0');
            exit();
        }
    }
}

// ================================================================
// REMOVE UNWANTED CLIENT AREA MENUS
// ================================================================

/**
 * Remove "Order list", "Shipments", "Files", and "Calendar" from client navigation
 * Keep only: Invoices, Cart, Products
 * 
 * @hook customers_area_navigation
 */
hooks()->add_filter('customers_area_navigation', 'menu_remover_filter_navigation');

function menu_remover_filter_navigation($nav)
{
    // List of menu slugs to remove
    $menus_to_remove = [
        // Order/Shipment menus
        'order_list',      // Order list menu
        'orderlist',       // Alternative slug
        'orders',          // Alternative slug
        'shipments',       // Shipments menu
        'shipment',        // Alternative slug
        
        // Files menu
        'files',           // Files menu
        'file',            // Alternative slug
        'documents',       // Alternative slug
        
        // Calendar menu
        'calendar',        // Calendar menu
        'calendars',       // Alternative slug
        'events',          // Alternative slug
    ];
    
    // Remove unwanted menus
    foreach ($nav as $key => $item) {
        // Check both array key and slug property
        $slug = isset($item['slug']) ? $item['slug'] : $key;
        
        // Remove if in our blacklist
        if (in_array($slug, $menus_to_remove, true)) {
            unset($nav[$key]);
            log_activity('Menu Remover: Removed menu item - ' . $slug);
        }
    }
    
    return $nav;
}

// ================================================================
// CSS + JAVASCRIPT FALLBACK (Hide menus via DOM)
// ================================================================

/**
 * Additional CSS/JS to hide menu items by href patterns
 * This is a fallback in case the hook filter doesn't catch everything
 * 
 * @hook app_customers_head
 */
hooks()->add_action('app_customers_head', 'menu_remover_add_client_css_js');

function menu_remover_add_client_css_js()
{
    ?>
    <style>
        /* ============================================
           Hide Order List, Shipments, Files, and Calendar Menu Items
           Using CSS selectors
           ============================================ */
        
        /* Hide by href patterns - Order/Shipments */
        a[href*="clients/order"],
        a[href*="clients/orderlist"],
        a[href*="clients/orders"],
        a[href*="clients/shipment"],
        a[href*="clients/shipments"],
        a[href*="/order_list"],
        a[href*="/orderlist"],
        
        /* Hide by href patterns - Files */
        a[href*="clients/files"],
        a[href*="clients/file"],
        a[href*="clients/documents"],
        a[href*="/files"],
        
        /* Hide by href patterns - Calendar */
        a[href*="clients/calendar"],
        a[href*="clients/calendars"],
        a[href*="clients/events"],
        a[href*="/calendar"] {
            display: none !important;
        }
        
        /* Hide by common class patterns - Order/Shipments */
        .customers-nav-item-orderlist,
        .customers-nav-item-order_list,
        .customers-nav-item-orders,
        .customers-nav-item-shipments,
        .customers-nav-item-shipment,
        
        /* Hide by common class patterns - Files */
        .customers-nav-item-files,
        .customers-nav-item-file,
        .customers-nav-item-documents,
        
        /* Hide by common class patterns - Calendar */
        .customers-nav-item-calendar,
        .customers-nav-item-calendars,
        .customers-nav-item-events,
        
        /* Hide <li> elements with these classes */
        li.customers-nav-item-orderlist,
        li.customers-nav-item-order_list,
        li.customers-nav-item-orders,
        li.customers-nav-item-shipments,
        li.customers-nav-item-shipment,
        li.customers-nav-item-files,
        li.customers-nav-item-file,
        li.customers-nav-item-documents,
        li.customers-nav-item-calendar,
        li.customers-nav-item-calendars,
        li.customers-nav-item-events {
            display: none !important;
        }
        
        /* Hide parent <li> elements containing these links */
        li:has(a[href*="clients/order"]),
        li:has(a[href*="clients/orderlist"]),
        li:has(a[href*="clients/orders"]),
        li:has(a[href*="clients/shipment"]),
        li:has(a[href*="clients/shipments"]),
        li:has(a[href*="clients/files"]),
        li:has(a[href*="clients/file"]),
        li:has(a[href*="clients/documents"]),
        li:has(a[href*="clients/calendar"]),
        li:has(a[href*="clients/calendars"]),
        li:has(a[href*="clients/events"]) {
            display: none !important;
        }
        
        /* ============================================
           Hide by data attributes (if used)
           ============================================ */
        [data-group="files"],
        [data-group="calendar"],
        [data-group="orders"],
        [data-group="shipments"] {
            display: none !important;
        }
    </style>
    
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            
            /**
             * Remove Order List, Shipments, Files, and Calendar menu items
             * Using JavaScript DOM manipulation
             */
            function removeUnwantedMenuItems() {
                
                // Get all navigation links
                var allLinks = document.querySelectorAll("a, .nav-link, .sidebar-menu a, .customers-nav-item a");
                
                // URL patterns to remove
                var urlsToRemove = [
                    // Order/Shipment patterns
                    "clients/order",
                    "clients/orderlist",
                    "clients/orders",
                    "order_list",
                    "orderlist",
                    "clients/shipment",
                    "clients/shipments",
                    "shipments",
                    
                    // Files patterns
                    "clients/files",
                    "clients/file",
                    "clients/documents",
                    "/files",
                    
                    // Calendar patterns
                    "clients/calendar",
                    "clients/calendars",
                    "clients/events",
                    "/calendar"
                ];
                
                // Text patterns to remove (case-insensitive)
                var textsToRemove = [
                    "order list",
                    "orderlist",
                    "orders",
                    "shipments",
                    "shipment",
                    "files",
                    "file",
                    "documents",
                    "calendar",
                    "calendars",
                    "events"
                ];
                
                allLinks.forEach(function(link) {
                    var href = link.getAttribute("href");
                    var linkText = link.textContent.toLowerCase().trim();
                    
                    var shouldRemove = false;
                    
                    // Check href patterns
                    if (href) {
                        urlsToRemove.forEach(function(urlPattern) {
                            if (href.indexOf(urlPattern) !== -1) {
                                shouldRemove = true;
                            }
                        });
                    }
                    
                    // Check text patterns (exact match)
                    textsToRemove.forEach(function(textPattern) {
                        if (linkText === textPattern || linkText.indexOf(textPattern) !== -1) {
                            shouldRemove = true;
                        }
                    });
                    
                    // Remove the menu item
                    if (shouldRemove) {
                        var parentLi = link.closest("li");
                        if (parentLi) {
                            parentLi.remove();
                            console.log("Menu Remover: Removed menu item - " + linkText);
                        } else {
                            // If no parent <li>, hide the link itself
                            link.style.display = 'none';
                            console.log("Menu Remover: Hidden menu link - " + linkText);
                        }
                    }
                });
                
                // Additional cleanup by class names
                var classesToRemove = [
                    'customers-nav-item-files',
                    'customers-nav-item-calendar',
                    'customers-nav-item-orderlist',
                    'customers-nav-item-shipments'
                ];
                
                classesToRemove.forEach(function(className) {
                    var elements = document.querySelectorAll('.' + className);
                    elements.forEach(function(el) {
                        el.remove();
                        console.log("Menu Remover: Removed by class - " + className);
                    });
                });
            }
            
            // Run immediately on page load
            removeUnwantedMenuItems();
            
            // Run again after delays (for dynamically loaded content)
            setTimeout(removeUnwantedMenuItems, 300);
            setTimeout(removeUnwantedMenuItems, 600);
            setTimeout(removeUnwantedMenuItems, 1000);
            
            // Listen for navigation changes (SPA support)
            if (window.MutationObserver) {
                var observer = new MutationObserver(function(mutations) {
                    removeUnwantedMenuItems();
                });
                
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
            
            // Listen for AJAX complete events (if using jQuery)
            if (window.jQuery) {
                jQuery(document).ajaxComplete(function() {
                    setTimeout(removeUnwantedMenuItems, 100);
                });
            }
            
            console.log("Menu Remover: Client area menu cleanup initialized");
            console.log("Menu Remover: Hiding - Order List, Shipments, Files, Calendar");
        });
    </script>
    <?php
}

// ================================================================
// ADMIN AREA NOTICE (Optional)
// ================================================================

/**
 * Show admin notice about active menu filtering
 * 
 * @hook admin_init
 */
hooks()->add_action('admin_init', 'menu_remover_admin_notice');

function menu_remover_admin_notice()
{
    $CI = &get_instance();
    
    // Only show to admins
    if (!is_admin()) {
        return;
    }
    
    // Only show on module setup page
    $current_url = $_SERVER['REQUEST_URI'];
    if (strpos($current_url, 'modules') !== false) {
        // You can add a notice here if needed
        // set_alert('info', 'Menu Remover module is active - hiding Order List, Shipments, Files, and Calendar from client area');
    }
}

// ================================================================
// ADDITIONAL PROTECTION: Hide via Sidebar Filter
// ================================================================

/**
 * Additional hook to remove items from sidebar if they persist
 * 
 * @hook customers_navigation_start
 */
hooks()->add_action('customers_navigation_start', 'menu_remover_sidebar_cleanup');

function menu_remover_sidebar_cleanup()
{
    ?>
    <script>
        // Additional cleanup for sidebar navigation
        (function() {
            var hiddenMenus = ['files', 'calendar', 'orders', 'orderlist', 'shipments'];
            
            hiddenMenus.forEach(function(menuSlug) {
                var menuItem = document.querySelector('[data-slug="' + menuSlug + '"]');
                if (menuItem) {
                    menuItem.remove();
                    console.log('Menu Remover: Removed sidebar item - ' + menuSlug);
                }
            });
        })();
    </script>
    <?php
}

// ================================================================
// OMNI SALES CUSTOMIZATION
// ================================================================

/**
 * Customize Omni Sales Client Page
 * - Hide quantity input field (class: form-control qty)
 * - Change "Add to Cart" button text to "Buy Now"
 * - Set quantity to 1 automatically
 * - Redirect to cart page after successful add to cart
 * 
 * @hook app_customers_head
 */
hooks()->add_action('app_customers_head', 'menu_remover_customize_omni_sales');

function menu_remover_customize_omni_sales()
{
    // Only run on omni_sales pages
    $current_url = $_SERVER['REQUEST_URI'];
    if (strpos($current_url, 'omni_sales') === false) {
        return;
    }
    
    ?>
    <style>
        /* ============================================
           OMNI SALES CUSTOMIZATIONS
           Hide Quantity Field & Style Buy Now Button
           ============================================ */
        
        /* Hide Quantity Input Field - Multiple Selectors */
        .form-control.qty,
        input.form-control.qty,
        input[type="number"].qty,
        .qty-field,
        .quantity-field,
        input[name*="quantity"],
        input[name="quantity"],
        input[placeholder*="Quantity"],
        input[placeholder*="quantity"],
        #quantity,
        .product-quantity,
        .item-quantity,
        .input-group:has(> #quantity),
        .form-group:has(> #quantity) {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            height: 0 !important;
            width: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            position: absolute !important;
            left: -9999px !important;
        }
        
        /* Hide quantity label if exists */
        label[for*="qty"],
        label[for*="quantity"],
        label:has(+ input.qty),
        label:has(+ input[name="quantity"]) {
            display: none !important;
        }
        
        /* Hide quantity wrapper/container */
        .qty-wrapper,
        .quantity-wrapper,
        .form-group:has(> input.qty),
        .form-group:has(> input[name="quantity"]),
        div:has(> input.qty):not(.product-item):not(.card):not(.row),
        div:has(> #quantity):not(.details):not(.action) {
            display: none !important;
        }
        
        /* Hide the entire form-group containing quantity input */
        .form-group.pull-left:has(#quantity),
        .form-group:has(.input-group):has(#quantity) {
            display: none !important;
        }
        
        /* ============================================
           Buy Now Button Styling - Modern & Eye-catching
           ============================================ */
        .add_cart.btn.btn-success,
        .add_to_cart.btn.btn-success,
        button.add_cart.btn-success,
        button.add_to_cart,
        button.add_cart,
        .add_cart,
        .add_to_cart,
        a.add_cart,
        a.add_to_cart {
            /* Modern gradient background - Red/Orange theme */
            background: linear-gradient(135deg, #FF6B6B 0%, #E74C3C 100%) !important;
            border: none !important;
            color: white !important;
            font-weight: 600 !important;
            padding: 12px 35px !important;
            font-size: 15px !important;
            border-radius: 8px !important;
            transition: all 0.3s ease !important;
            text-transform: uppercase !important;
            letter-spacing: 0.8px !important;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3) !important;
            cursor: pointer !important;
            position: relative !important;
            overflow: hidden !important;
        }
        
        /* Hover Effect */
        .add_cart.btn.btn-success:hover,
        .add_to_cart:hover,
        button.add_cart:hover,
        .add_cart:hover,
        a.add_cart:hover,
        a.add_to_cart:hover {
            background: linear-gradient(135deg, #E74C3C 0%, #C0392B 100%) !important;
            transform: translateY(-3px) !important;
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.5) !important;
        }
        
        /* Active/Click Effect */
        .add_cart.btn.btn-success:active,
        .add_to_cart:active,
        button.add_cart:active,
        .add_cart:active,
        a.add_cart:active,
        a.add_to_cart:active {
            transform: translateY(-1px) !important;
            box-shadow: 0 3px 10px rgba(231, 76, 60, 0.4) !important;
        }
        
        /* Icon spacing */
        .add_cart i,
        .add_to_cart i,
        button.add_cart i {
            margin-right: 8px !important;
            font-size: 14px !important;
        }
        
        /* Ripple effect animation */
        .add_cart::before,
        .add_to_cart::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .add_cart:active::before,
        .add_to_cart:active::before {
            width: 300px;
            height: 300px;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .add_cart.btn.btn-success,
            .add_to_cart,
            button.add_cart,
            .add_cart {
                padding: 10px 25px !important;
                font-size: 14px !important;
            }
        }
    </style>
    
    <script>
        (function() {
            'use strict';
            
            console.log('Omni Sales Customizer: Starting initialization...');
            
            // Track if we should redirect
            window.omniShouldRedirectToCart = false;
            
            /**
             * Hide all quantity input fields
             */
            function hideQuantityFields() {
                var selectors = [
                    '.form-control.qty',
                    'input.qty',
                    'input[type="number"].qty',
                    'input[name*="quantity"]',
                    'input[name="quantity"]',
                    'input[name*="qty"]',
                    '.quantity-field',
                    '.qty-field',
                    '#quantity',
                    '.product-quantity'
                ];
                
                var hiddenCount = 0;
                
                selectors.forEach(function(selector) {
                    var elements = document.querySelectorAll(selector);
                    
                    elements.forEach(function(element) {
                        if (element.tagName === 'INPUT') {
                            element.value = '1';
                            element.setAttribute('value', '1');
                            
                            element.addEventListener('change', function() {
                                this.value = '1';
                            });
                            element.addEventListener('input', function() {
                                this.value = '1';
                            });
                        }
                        
                        element.style.display = 'none';
                        element.style.visibility = 'hidden';
                        element.style.opacity = '0';
                        element.style.height = '0';
                        element.style.width = '0';
                        element.style.position = 'absolute';
                        element.style.left = '-9999px';
                        
                        var parent = element.closest('.form-group, .qty-wrapper, .quantity-wrapper, .input-group');
                        if (parent) {
                            var siblings = parent.querySelectorAll('input, select, textarea');
                            if (siblings.length === 1) {
                                parent.style.display = 'none';
                            }
                        }
                        
                        hiddenCount++;
                    });
                });
                
                if (hiddenCount > 0) {
                    console.log('Omni Sales Customizer: Hidden ' + hiddenCount + ' quantity fields');
                }
                
                return hiddenCount;
            }
            
            /**
             * Change "Add to Cart" button text to "Buy Now"
             */
            function changeButtonText() {
                var selectors = [
                    '.add_cart',
                    '.add_to_cart',
                    'button.add_cart',
                    'button.add_to_cart',
                    'a.add_cart',
                    'a.add_to_cart',
                    '.btn.add_cart',
                    'input.add_cart',
                    '[class*="add_cart"]',
                    '[class*="add_to_cart"]'
                ];
                
                var changedCount = 0;
                
                selectors.forEach(function(selector) {
                    var buttons = document.querySelectorAll(selector);
                    
                    buttons.forEach(function(button) {
                        var originalText = '';
                        
                        if (button.tagName === 'INPUT') {
                            originalText = button.value || '';
                        } else {
                            originalText = button.textContent || button.innerText || '';
                        }
                        
                        originalText = originalText.trim().toLowerCase();
                        
                        if (originalText.indexOf('add') !== -1 || 
                            originalText.indexOf('cart') !== -1 ||
                            originalText === '' ||
                            originalText.length < 3) {
                            
                            var existingIcon = button.querySelector('i, .fa, .icon');
                            var iconHTML = '<i class="fa fa-shopping-bag"></i> ';
                            
                            if (existingIcon) {
                                iconHTML = '';
                            }
                            
                            if (button.tagName === 'INPUT') {
                                button.value = 'Buy Now';
                                button.setAttribute('value', 'Buy Now');
                            } else {
                                button.innerHTML = iconHTML + 'Buy Now';
                            }
                            
                            button.setAttribute('title', 'Buy this product now');
                            
                            changedCount++;
                        }
                    });
                });
                
                if (changedCount > 0) {
                    console.log('Omni Sales Customizer: Changed ' + changedCount + ' buttons to "Buy Now"');
                }
                
                return changedCount;
            }
            
            /**
             * Set default quantity to 1 for all forms
             */
            function setDefaultQuantity() {
                var qtyInputs = document.querySelectorAll('input.qty, input[name*="quantity"], input[name="quantity"], #quantity');
                
                qtyInputs.forEach(function(input) {
                    input.value = '1';
                    input.setAttribute('value', '1');
                    input.setAttribute('min', '1');
                    input.setAttribute('max', '1');
                    input.readOnly = true;
                    
                    ['change', 'input', 'keyup', 'keydown', 'paste'].forEach(function(eventType) {
                        input.addEventListener(eventType, function(e) {
                            if (this.value !== '1') {
                                this.value = '1';
                                e.preventDefault();
                                e.stopPropagation();
                            }
                        });
                    });
                });
            }
            
            /**
             * Monitor for successful cart addition and redirect
             */
            function monitorCartSuccess() {
                console.log('Omni Sales Customizer: Setting up cart success monitor');
                
                // Method 1: Watch for success modal
                var checkModalInterval = setInterval(function() {
                    var successModal = document.querySelector('#alert_add');
                    var successDiv = document.querySelector('.add_success');
                    
                    if (successModal && successDiv && !successDiv.classList.contains('hide')) {
                        console.log('Omni Sales Customizer: Product added successfully detected!');
                        clearInterval(checkModalInterval);
                        
                        // Close modal if jQuery is available
                        if (window.jQuery && typeof jQuery('#alert_add').modal === 'function') {
                            jQuery('#alert_add').modal('hide');
                        }
                        
                        // Redirect to cart
                        setTimeout(function() {
                            var cartUrl = '<?php echo site_url("omni_sales/omni_sales_client/view_cart"); ?>';
                            console.log('Omni Sales Customizer: Redirecting to cart:', cartUrl);
                            window.location.href = cartUrl;
                        }, 500);
                    }
                }, 200);
                
                // Stop checking after 10 seconds
                setTimeout(function() {
                    clearInterval(checkModalInterval);
                }, 10000);
                
                // Method 2: Watch for cookie changes (cart_id_list)
                var originalCookie = document.cookie;
                var checkCookieInterval = setInterval(function() {
                    var currentCookie = document.cookie;
                    if (currentCookie !== originalCookie && currentCookie.indexOf('cart_id_list') !== -1) {
                        console.log('Omni Sales Customizer: Cart cookie changed - product added!');
                        clearInterval(checkCookieInterval);
                        
                        setTimeout(function() {
                            var cartUrl = '<?php echo site_url("omni_sales/omni_sales_client/view_cart"); ?>';
                            console.log('Omni Sales Customizer: Redirecting to cart:', cartUrl);
                            window.location.href = cartUrl;
                        }, 800);
                    }
                }, 200);
                
                // Stop checking after 10 seconds
                setTimeout(function() {
                    clearInterval(checkCookieInterval);
                }, 10000);
                
                // Method 3: Watch for "added" button appearance
                var checkAddedButton = setInterval(function() {
                    var addedButton = document.querySelector('.added_to_cart:not(.hide)');
                    if (addedButton) {
                        console.log('Omni Sales Customizer: "Added" button appeared!');
                        clearInterval(checkAddedButton);
                        
                        setTimeout(function() {
                            var cartUrl = '<?php echo site_url("omni_sales/omni_sales_client/view_cart"); ?>';
                            console.log('Omni Sales Customizer: Redirecting to cart:', cartUrl);
                            window.location.href = cartUrl;
                        }, 800);
                    }
                }, 200);
                
                // Stop checking after 10 seconds
                setTimeout(function() {
                    clearInterval(checkAddedButton);
                }, 10000);
            }
            
            /**
             * Setup click handlers on Buy Now buttons
             */
            function setupBuyNowButtons() {
                var buttons = document.querySelectorAll('.add_cart, .add_to_cart, button.add_cart, button.add_to_cart');
                
                buttons.forEach(function(button) {
                    // Add our own click handler that triggers after the original
                    button.addEventListener('click', function(e) {
                        console.log('Omni Sales Customizer: Buy Now button clicked');
                        
                        // Ensure quantity is set to 1
                        var qtyInput = document.querySelector('#quantity, input.qty, input[name="quantity"]');
                        if (qtyInput) {
                            qtyInput.value = '1';
                        }
                        
                        // Start monitoring for successful add
                        monitorCartSuccess();
                    }, false);
                });
                
                console.log('Omni Sales Customizer: Setup ' + buttons.length + ' Buy Now buttons');
            }
            
            /**
             * Main initialization function
             */
            function initCustomizations() {
                var qtyCount = hideQuantityFields();
                var btnCount = changeButtonText();
                setDefaultQuantity();
                setupBuyNowButtons();
                
                if (qtyCount > 0 || btnCount > 0) {
                    console.log('Omni Sales Customizer: Applied customizations successfully');
                }
            }
            
            // Run on DOM ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initCustomizations);
            } else {
                initCustomizations();
            }
            
            // Run again after delays (for AJAX content)
            setTimeout(initCustomizations, 300);
            setTimeout(initCustomizations, 600);
            setTimeout(initCustomizations, 1000);
            setTimeout(initCustomizations, 1500);
            
            // Watch for dynamic content changes
            if (window.MutationObserver) {
                var observer = new MutationObserver(function(mutations) {
                    var shouldRun = false;
                    
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes.length > 0) {
                            mutation.addedNodes.forEach(function(node) {
                                if (node.nodeType === 1) {
                                    if (node.classList && 
                                        (node.classList.contains('product') || 
                                         node.classList.contains('add_cart') ||
                                         node.classList.contains('add_to_cart') ||
                                         node.querySelector('.add_cart') ||
                                         node.querySelector('.add_to_cart') ||
                                         node.querySelector('.qty'))) {
                                        shouldRun = true;
                                    }
                                }
                            });
                        }
                    });
                    
                    if (shouldRun) {
                        setTimeout(initCustomizations, 100);
                    }
                });
                
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
            
            // Listen for AJAX complete events (jQuery)
            if (window.jQuery) {
                jQuery(document).ajaxComplete(function() {
                    setTimeout(initCustomizations, 100);
                });
            }
            
            console.log('Omni Sales Customizer: Initialization complete');
            console.log('Omni Sales Customizer: Monitoring for dynamic content...');
        })();
    </script>
    <?php
}

// ================================================================
// INTERCEPT ADD TO CART - SET QUANTITY TO 1
// ================================================================

/**
 * Hook into form submission to ensure quantity is always 1
 * This is a backup in case JavaScript is disabled
 * 
 * @hook pre_controller
 */
hooks()->add_action('pre_controller', 'menu_remover_force_quantity_one');

function menu_remover_force_quantity_one()
{
    $CI = &get_instance();
    
    // Check if this is an omni_sales request
    $current_controller = $CI->router->fetch_class();
    
    if (strpos($current_controller, 'omni_sales') !== false) {
        
        // Force quantity to 1 in POST data
        if (isset($_POST['quantity'])) {
            $_POST['quantity'] = 1;
            $CI->input->set_post('quantity', 1);
        }
        
        // Force quantity to 1 in GET data
        if (isset($_GET['quantity'])) {
            $_GET['quantity'] = 1;
        }
        
        // Force quantity to 1 in REQUEST data
        if (isset($_REQUEST['quantity'])) {
            $_REQUEST['quantity'] = 1;
        }
        
        log_activity('Menu Remover: Forced quantity to 1 for Omni Sales');
    }
}