// Cari ID control Elementor yang terdaftar dua kali di class-elementor-widget.php.
// add_text_style('x') membuat x_typography + x_color; add_badge_box_controls('x') membuat x_margin/_padding/_bg/_radius;
// add_spacing_controls('x') membuat x_margin/_padding.
const fs = require('fs');
const src = fs.readFileSync(process.argv[2], 'utf8');
const ids = [];
let m;

const direct = /(?:add_control|add_responsive_control|add_box_control|add_alignment_control|add_bg_control|start_controls_section|start_controls_tab|start_controls_tabs)\(\s*'([a-z0-9_]+)'/g;
while ((m = direct.exec(src))) ids.push(m[1]);

const group = /'name'\s*=>\s*'([a-z0-9_]+)'/g;
while ((m = group.exec(src))) ids.push(m[1]);

const text = /add_text_style\(\s*'([a-z0-9_]+)'/g;
while ((m = text.exec(src))) ids.push(m[1] + '_typography', m[1] + '_color');

const badge = /add_badge_box_controls\(\s*'([a-z0-9_]+)'/g;
while ((m = badge.exec(src))) ids.push(m[1] + '_margin', m[1] + '_padding', m[1] + '_bg', m[1] + '_radius');

const spacing = /add_spacing_controls\(\s*'([a-z0-9_]+)'/g;
while ((m = spacing.exec(src))) ids.push(m[1] + '_margin', m[1] + '_padding');

// Repeater fields ('section_*') are in their own namespace; ignore them here.
const seen = {};
ids.forEach((id) => { seen[id] = (seen[id] || 0) + 1; });
const dups = Object.keys(seen).filter((id) => seen[id] > 1);
console.log('total id:', ids.length, '| duplikat:', dups.length ? dups.join(', ') : 'tidak ada');
