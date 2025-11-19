<style>
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --dark-bg: #0f172a;
            --dark-card: #1e293b;
            --dark-hover: #334155;
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --border-color: #334155;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-color: var(--dark-bg);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Sidebar */
        .admin-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 280px;
            background-color: var(--dark-card);
            border-right: 1px solid var(--border-color);
            padding: 20px;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .admin-sidebar-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .admin-sidebar-header h4 {
            color: var(--primary);
            margin: 0;
            font-weight: 700;
        }
        
        .admin-nav {
            list-style: none;
            padding: 0;
        }
        
        .admin-nav-item {
            margin-bottom: 8px;
        }
        
        .admin-nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .admin-nav-link:hover {
            background-color: var(--dark-hover);
            color: var(--text-primary);
        }
        
        .admin-nav-link.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }
        
        .admin-nav-link i {
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .admin-main {
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .admin-header h1 {
            color: var(--text-primary);
            font-size: 2rem;
            margin: 0;
        }
        
        .stat-card {
            background: var(--dark-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.2);
            border-color: var(--primary);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .stat-icon.primary { background: rgba(16, 185, 129, 0.2); color: var(--primary); }
        .stat-icon.info { background: rgba(59, 130, 246, 0.2); color: var(--info); }
        .stat-icon.warning { background: rgba(245, 158, 11, 0.2); color: var(--warning); }
        .stat-icon.danger { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .card {
            background: var(--dark-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .card-header h5 {
            color: var(--text-primary);
            margin: 0;
        }
        
        .table-dark {
            background-color: var(--dark-card);
            color: var(--text-primary);
        }
        
        .table-dark th {
            background-color: var(--dark-hover);
            border-color: var(--border-color);
            color: var(--text-primary);
        }
        
        .table-dark td {
            border-color: var(--border-color);
            color: var(--text-primary);
        }
        
        .form-control, .form-select {
            background-color: var(--dark-hover);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .form-control:focus, .form-select:focus {
            background-color: var(--dark-hover);
            border-color: var(--primary);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.25);
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .modal-content {
            background-color: var(--dark-card);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .modal-header, .modal-footer {
            border-color: var(--border-color);
        }
        
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            
            .admin-main {
                margin-left: 0;
            }
        }
    </style>

