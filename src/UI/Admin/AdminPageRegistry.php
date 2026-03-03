<?php

declare(strict_types=1);

namespace Pet\UI\Admin;

class AdminPageRegistry
{
    private string $pluginPath;
    private string $pluginUrl;

    public function __construct(string $pluginPath, string $pluginUrl)
    {
        $this->pluginPath = rtrim($pluginPath, '/');
        $this->pluginUrl = rtrim($pluginUrl, '/');
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
    }

    public function addMenuPage(): void
    {
        // Top Level Menu
        add_menu_page(
            'PET Overview',
            'PET',
            'manage_options',
            'pet-dashboard',
            [$this, 'renderPage'],
            'dashicons-chart-area',
            25
        );

        $submenus = [
            'pet-dashboard' => 'Overview', // Rename first item
            'pet-dashboards' => 'Dashboards',
            'pet-crm' => 'Customers',
            'pet-quotes-sales' => 'Quotes & Sales',
            'pet-finance' => 'Finance',
            'pet-delivery' => 'Delivery',
            'pet-time' => 'Time',
            'pet-support' => 'Support',
            'pet-conversations' => 'Conversations',
            'pet-approvals' => 'Approvals',
            'pet-knowledge' => 'Knowledge',
            'pet-people' => 'Staff',
            'pet-roles' => 'Roles & Capabilities',
            'pet-activity' => 'Activity',
            'pet-settings' => 'Settings',
            'pet-pulseway' => 'Pulseway RMM',
            'pet-shortcodes' => 'Shortcodes',
            'pet-demo-tools' => 'Demo Tools',
        ];

        foreach ($submenus as $slug => $title) {
            add_submenu_page(
                'pet-dashboard',
                'PET - ' . $title,
                $title,
                'manage_options',
                $slug,
                [$this, 'renderPage']
            );
        }
    }

    public function renderPage(): void
    {
        $page = $_GET['page'] ?? 'pet-dashboard';
        if ($page === 'pet-demo-tools') {
            $nonce = wp_create_nonce('wp_rest');
            echo '<div class="wrap"><h1>Demo Tools</h1>';
            echo '<p>Last seed_run_id: <span id="pet-demo-last-id"></span></p>';
            echo '<button id="pet-demo-seed" class="button button-primary">Seed Full Demo Data</button> ';
            echo '<button id="pet-demo-purge" class="button">Purge Last Seed Run</button>';
            echo '<pre id="pet-demo-result" style="margin-top:16px; padding:12px; background:#fff; border:1px solid #ccd0d4; max-height:300px; overflow:auto;"></pre>';
            echo '<script>';
            echo 'window.petSettings = window.petSettings || {}; window.petSettings.nonce = window.petSettings.nonce || "' . esc_js($nonce) . '";';
            echo '(function(){';
            echo 'var lastIdKey="pet_demo_last_seed_run_id";';
            echo 'var lastId=localStorage.getItem(lastIdKey)||"";';
            echo 'var lastIdEl=document.getElementById("pet-demo-last-id");';
            echo 'var seedBtn=document.getElementById("pet-demo-seed");';
            echo 'var purgeBtn=document.getElementById("pet-demo-purge");';
            echo 'var resultEl=document.getElementById("pet-demo-result");';
            echo 'function showLast(){ lastIdEl.textContent=lastId||"(none)"; }';
            echo 'function setBusy(b){ seedBtn.disabled=b; purgeBtn.disabled=b; }';
            echo 'async function seed(){ setBusy(true); resultEl.textContent=""; try {';
            echo 'var res=await fetch("' . esc_url_raw(rest_url('pet/v1/system/demo/seed_full')) . '",{method:"POST",headers:{"X-WP-Nonce":window.petSettings.nonce}});';
            echo 'var txt=await res.text(); resultEl.textContent="HTTP "+res.status+"\\n\\n"+txt;';
            echo 'try{ var j=JSON.parse(txt); if(j && j.seed_run_id){ lastId=j.seed_run_id; localStorage.setItem(lastIdKey,lastId); showLast(); } }catch(e){}';
            echo '} finally { setBusy(false); } }';
            echo 'async function purge(){ if(!lastId){ alert("No seed_run_id saved. Run Seed first."); return; } if(!confirm("Purge seed_run_id: "+lastId+" ?")){ return; } setBusy(true); resultEl.textContent=""; try {';
            echo 'var res=await fetch("' . esc_url_raw(rest_url('pet/v1/system/demo/purge')) . '",{method:"POST",headers:{"X-WP-Nonce":window.petSettings.nonce,"Content-Type":"application/json"},body:JSON.stringify({seed_run_id:lastId})});';
            echo 'var txt=await res.text(); resultEl.textContent="HTTP "+res.status+"\\n\\n"+txt;';
            echo '} finally { setBusy(false); } }';
            echo 'seedBtn.addEventListener("click",seed); purgeBtn.addEventListener("click",purge); showLast();';
            echo '})();';
            echo '</script></div>';
            return;
        }
        if ($page === 'pet-shortcodes') {
            echo '<div class="wrap"><h1>PET Shortcodes</h1>';
            echo '<p>Use these shortcodes inside pages or posts. Copy them into your content to embed PET widgets.</p>';

            $shortcodes = [
                [
                    'tag' => 'pet_my_profile',
                    'name' => 'My Profile',
                    'description' => 'Shows the current user profile with roles, skills, and certifications.',
                    'example' => '[pet_my_profile]',
                ],
                [
                    'tag' => 'pet_my_work',
                    'name' => 'My Work',
                    'description' => 'Lists work items assigned to the current user, grouped by source.',
                    'example' => '[pet_my_work]',
                ],
                [
                    'tag' => 'pet_my_calendar',
                    'name' => 'My Calendar',
                    'description' => 'Shows upcoming scheduled work items for the current user (next 14 days).',
                    'example' => '[pet_my_calendar]',
                ],
                [
                    'tag' => 'pet_activity_stream',
                    'name' => 'Activity Stream',
                    'description' => 'Displays recent PET activity relevant to the current user.',
                    'example' => '[pet_activity_stream limit="20"]',
                ],
            ];

            echo '<table class="widefat striped" style="max-width:900px;margin-top:16px;">';
            echo '<thead><tr>';
            echo '<th style="width:180px;">Shortcode</th>';
            echo '<th>Description</th>';
            echo '<th style="width:260px;">Usage</th>';
            echo '</tr></thead><tbody>';
            foreach ($shortcodes as $row) {
                echo '<tr>';
                echo '<td><strong>' . esc_html($row['name']) . '</strong><br /><code>[' . esc_html($row['tag']) . ']</code></td>';
                echo '<td>' . esc_html($row['description']) . '</td>';
                echo '<td>';
                echo '<code class="pet-shortcode-example">' . esc_html($row['example']) . '</code><br />';
                echo '<button type="button" class="button pet-copy-shortcode" data-shortcode="' . esc_attr($row['example']) . '">Copy</button>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';

            echo '<script>';
            echo '(function(){';
            echo 'function copyShortcode(value){';
            echo 'if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(value).catch(function(){});return;}';
            echo 'var ta=document.createElement("textarea");ta.value=value;document.body.appendChild(ta);ta.select();try{document.execCommand("copy");}catch(e){}document.body.removeChild(ta);}';
            echo 'document.addEventListener("click",function(e){var t=e.target;if(!t.classList.contains("pet-copy-shortcode"))return;e.preventDefault();var v=t.getAttribute("data-shortcode")||"";if(!v)return;copyShortcode(v);t.textContent="Copied";setTimeout(function(){t.textContent="Copy";},1500);});';
            echo '})();';
            echo '</script>';
            echo '</div>';
            return;
        }
        echo '<div id="pet-admin-root"></div>';
        if ($page === 'pet-support') {
            echo '<script>';
            echo '(function(){';
            echo 'function setHashFromRow(el){try{var row=el.closest("tr");if(!row)return;var cells=Array.prototype.slice.call(row.children||[]);var idVal=null;for(var i=0;i<cells.length;i++){var txt=(cells[i].textContent||"").trim();var m=txt.match(/^(\\d{1,})$/);if(m&&m[1]){idVal=m[1];break;}}if(idVal){history.replaceState(null,"",location.pathname+location.search+"#ticket="+idVal);}}catch(_){}}';
            echo 'document.addEventListener("click",function(e){';
            echo 'var t=e.target;if(!t||!t.closest)return;';
            echo 'var a=t.closest(".pet-support a[href=\"#\"]");';
            echo 'if(a){setHashFromRow(a);e.preventDefault();}';
            echo 'var b=t.closest(".pet-support button");';
            echo 'if(b){var ty=(b.getAttribute("type")||"").toLowerCase();setHashFromRow(b);if(!ty||ty==="submit"){b.setAttribute("type","button");}}';
            echo '});';
            echo '})();';
            echo '</script>';
        }
    }

    public function enqueueScripts(string $hook): void
    {
        // Check if we are on a PET page
        // Top level is 'toplevel_page_pet-dashboard'
        // Submenus are usually 'pet_page_{slug}'
        if (strpos($hook, 'page_pet-') === false) {
            return;
        }

        wp_enqueue_media();

        $manifestPath = $this->pluginPath . '/dist/.vite/manifest.json';
        
        if (!file_exists($manifestPath)) {
            echo '<div class="error"><p>PET Plugin Error: Build manifest not found. Please run npm run build.</p></div>';
            return;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $entryKey = 'src/UI/Admin/main.tsx';
        
        if (isset($manifest[$entryKey])) {
            $file = $manifest[$entryKey]['file'];
            $cssFiles = $manifest[$entryKey]['css'] ?? [];

            wp_enqueue_script(
                'pet-admin-app',
                $this->pluginUrl . '/dist/' . $file,
                [],
                '1.0.2.' . time(), // Force cache bust
                true
            );

            // Inline safety: prevent accidental submits/navigation inside PET Support.
            // Ensures old cached bundles cannot trigger full-page reloads when clicking action buttons/links.
            $inline = <<<'JS'
document.addEventListener('click', function(e){
  var root = document.getElementById('pet-admin-root');
  if(!root) return;
  var t = e.target;
  if(!(t instanceof Element)) return;
  function setHashFromRow(el){
    try{
      var row = el.closest('tr');
      if(!row) return;
      var cells = Array.prototype.slice.call(row.children || []);
      var idVal = null;
      for(var i=0;i<cells.length;i++){
        var txt = (cells[i].textContent||'').trim();
        var m = txt.match(/^(\d{1,})$/);
        if(m && m[1]){
          idVal = m[1];
          break;
        }
      }
      if(idVal){
        history.replaceState(null,'',location.pathname+location.search+'#ticket='+idVal);
      }
    }catch(_){}
  }
  // Neutralize anchor hashes inside Support UI
  var a = t.closest('.pet-support a[href="#"]');
  if(a){ setHashFromRow(a); e.preventDefault(); }
  // Ensure all buttons inside Support act as non-submit buttons
  var b = t.closest('.pet-support button');
  if(b){
    var ty = (b.getAttribute('type')||'').toLowerCase();
    // Set hash for any button inside Support rows to allow restore if reload happens
    setHashFromRow(b);
    if(!ty || ty === 'submit'){ b.setAttribute('type','button'); }
  }
}, false);
JS;
            wp_add_inline_script('pet-admin-app', $inline, 'after');

            // Get current page slug from $_GET['page']
            $currentPage = $_GET['page'] ?? 'pet-dashboard';

            // Hide WordPress chrome on the Dashboards page for a standalone-app look
            if ($currentPage === 'pet-dashboards') {
                $chromeHideCSS = '
                    #wpadminbar, #adminmenumain, #adminmenuback, #adminmenuwrap, #wpfooter { display: none !important; }
                    #wpcontent, #wpbody-content { margin-left: 0 !important; padding-left: 0 !important; }
                    #wpbody { padding-top: 0 !important; }
                    html.wp-toolbar { padding-top: 0 !important; }
                    #pet-admin-root { margin: 0; padding: 0; }
                    .pet-admin-dashboard { padding: 0 !important; margin-top: 0 !important; }
                ';
                wp_add_inline_style('pet-admin-style', $chromeHideCSS);
            }

            wp_localize_script('pet-admin-app', 'petSettings', [
                'apiUrl' => rest_url('pet/v1'),
                'nonce' => wp_create_nonce('wp_rest'),
                'currentPage' => $currentPage,
                'currentUserId' => get_current_user_id(),
            ]);

            foreach ($cssFiles as $cssFile) {
                wp_enqueue_style(
                    'pet-admin-style',
                    $this->pluginUrl . '/dist/' . $cssFile,
                    [],
                    '1.0.2.' . time() // Force cache bust
                );
            }
        }
    }
}
