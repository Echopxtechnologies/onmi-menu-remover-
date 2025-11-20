<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Menu Remover Controller
 * 
 * This controller is optional but good practice for future expansion
 * Currently, all functionality is handled via hooks in the main module file
 */
class Menu_remover extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        
        // Only admins can access this controller
        if (!is_admin()) {
            access_denied('Menu Remover Admin');
        }
    }
    
    /**
     * Default index method
     * Shows module information and settings
     */
    public function index()
    {
        $data['title'] = 'Menu Remover - Settings';
        
        $this->load->view('menu_remover/index', $data);
    }
    
    /**
     * Get current filtered menus (for debugging)
     * 
     * @return void
     */
    public function debug_menus()
    {
        // Get client area navigation
        $nav = get_customers_area_navigation();
        
        echo '<h3>Current Client Area Navigation</h3>';
        echo '<pre>';
        print_r($nav);
        echo '</pre>';
        
        echo '<hr>';
        echo '<h3>Menus Being Removed</h3>';
        echo '<ul>';
        echo '<li>Order list</li>';
        echo '<li>Orderlist</li>';
        echo '<li>Orders</li>';
        echo '<li>Shipments</li>';
        echo '<li>Shipment</li>';
        echo '</ul>';
    }
}