/*PRODUCT DATA*/
const PRODUCTS = [
  // ── Electronics ──
  { id:1, name:"Galaxy Pro Laptop 15\"", cat:"electronics", emoji:"💻", image:"../assets/images/galaxybook.webp", company:"TechZone Lanka", price:189000, oldPrice:210000, rating:4.7, reviews:142, badge:"hot", isNew:false, tags:["Laptop","Core i7","16GB RAM"], desc:"High-performance laptop ideal for business and design work. Imported and verified by TechZone Lanka, Colombo's leading electronics distributor.", delivery:"Colombo: 1–2 days · Island: 3–5 days", isApiBacked:false },
  { id:2, name:"Wireless Noise-Cancel Headphones", cat:"electronics", emoji:"🎧", image:"../assets/images/sony.jpg", company:"SoundCraft PVT", price:14500, oldPrice:18000, rating:4.5, reviews:87, badge:"sale", isNew:false, tags:["Audio","Wireless","Bluetooth"], desc:"Premium noise-cancelling headphones with 30-hour battery life. Perfect for professionals working from home or in offices.", delivery:"Island-wide: 2–4 days" },
  { id:3, name:"Smart CCTV 4-Camera Kit", cat:"electronics", emoji:"📹", image:"../assets/images/cctv.jpg", company:"SecureVision SL", price:32000, oldPrice:null, rating:4.6, reviews:54, badge:"", isNew:true, tags:["Security","HD","WiFi"], desc:"Full HD smart CCTV system with remote mobile monitoring. Ideal for small businesses and shops across Sri Lanka.", delivery:"Colombo: 2 days · Other: 4–6 days" },
  { id:4, name:"Power Backup UPS 2000VA", cat:"electronics", emoji:"🔌", image:"../assets/images/UPS.webp", company:"PowerGuard Lanka", price:21500, oldPrice:null, rating:4.4, reviews:33, badge:"", isNew:false, tags:["UPS","Power","Office"], desc:"Reliable UPS for Sri Lanka's power fluctuation challenges. Protects computers and equipment during outages.", delivery:"Island-wide: 3–5 days" },
  { id:5, name:"POS System Touch Terminal", cat:"electronics", emoji:"🖥️", image:"../assets/images/POS-System.jpg", company:"CashFlow Systems", price:55000, oldPrice:65000, rating:4.8, reviews:29, badge:"hot", isNew:false, tags:["POS","Retail","Touch"], desc:"Complete point-of-sale terminal for retail shops, restaurants, and small businesses. Integrates with BizLink CRM.", delivery:"Colombo: Same day · Island: 3–5 days" },

  // ── Fashion ──
  { id:6, name:"Handloom Cotton Saree", cat:"fashion", emoji:"👘", image:"../assets/images/saree.jpg", company:"Kandy Weaves", price:4800, oldPrice:6200, rating:4.9, reviews:218, badge:"hot", isNew:false, tags:["Saree","Handloom","Traditional"], desc:"Authentic handloom cotton sarees from Kandy's master weavers. Each piece is unique and directly from the artisan.", delivery:"Island-wide: 2–4 days" },
  { id:7, name:"Men's Linen Business Shirt", cat:"fashion", emoji:"👔", image:"../assets/images/mens-linen-shirt.webp", company:"Colombo Threads", price:2800, oldPrice:null, rating:4.3, reviews:76, badge:"new", isNew:true, tags:["Shirt","Business","Linen"], desc:"Premium linen business shirts designed for Sri Lanka's tropical climate. Professional yet comfortable all day.", delivery:"Island-wide: 2–3 days" },
  { id:8, name:"Batik Casual Dress", cat:"fashion", emoji:"👗", image:"../assets/images/batik.webp", company:"Batik Isle Designs", price:3500, oldPrice:4200, rating:4.7, reviews:103, badge:"sale", isNew:false, tags:["Batik","Dress","Casual"], desc:"Vibrant hand-painted batik dresses by Sri Lankan artisans. Lightweight and perfect for island life.", delivery:"Island-wide: 2–4 days" },
  { id:9, name:"Leather Sandals – Handmade", cat:"fashion", emoji:"👡", image:"../assets/images/shoes.webp", company:"Galle Craft Studio", price:1950, oldPrice:null, rating:4.5, reviews:62, badge:"", isNew:false, tags:["Sandals","Leather","Handmade"], desc:"Comfortable hand-stitched leather sandals made in Galle by local craftsmen. Long-lasting and stylish.", delivery:"Island-wide: 3–5 days" },

  // ── Home ──
  { id:10, name:"Teak Wood Coffee Table", cat:"home", emoji:"🪵", image:"../assets/images/TeakWoodCoffeeTable.webp", company:"Mahogany Furniture SL", price:28500, oldPrice:35000, rating:4.8, reviews:44, badge:"", isNew:false, tags:["Furniture","Teak","Living Room"], desc:"Solid teak wood coffee table handcrafted by skilled Sri Lankan carpenters. Durable and elegant for any home.", delivery:"Colombo: 3–5 days · Island: 1–2 weeks" },
  { id:11, name:"Coconut Shell Decor Set", cat:"home", emoji:"🥥", image:"../assets/images/coconut.webp", company:"EcoHomeLanka", price:1200, oldPrice:null, rating:4.4, reviews:91, badge:"new", isNew:true, tags:["Decor","Eco","Coconut"], desc:"Beautifully crafted coconut shell decorative items — bowls, candle holders, and sculptures made by rural artisans.", delivery:"Island-wide: 2–3 days" },
  { id:12, name:"Solar LED Garden Lights (Set 6)", cat:"home", emoji:"💡", image:"../assets/images/solarlight.jpg", company:"GreenEnergy SL", price:5400, oldPrice:7200, rating:4.6, reviews:57, badge:"sale", isNew:false, tags:["Solar","Garden","LED"], desc:"Waterproof solar-powered garden lights. Install without wiring — ideal for Sri Lankan homes and small guesthouses.", delivery:"Island-wide: 2–4 days" },

  // ── Grocery ──
  { id:13, name:"Ceylon Black Tea – 1kg Premium", cat:"grocery", emoji:"🍵", image:"../assets/images/Ceylon Black Tea – 1kg Premium.webp", company:"Nuwara Tea Estates", price:1850, oldPrice:2200, rating:4.9, reviews:387, badge:"hot", isNew:false, tags:["Tea","Ceylon","Premium"], desc:"Authentic Ceylon black tea from Nuwara Eliya highlands. The pride of Sri Lanka — rich aroma, full-bodied flavor.", delivery:"Island-wide: 2–3 days" },
  { id:14, name:"Cold-Pressed Coconut Oil 1L", cat:"grocery", emoji:"🫙", image:"../assets/images/Cold-Pressed Coconut Oil 1L.webp", company:"Pure Coconut Lanka", price:950, oldPrice:null, rating:4.7, reviews:204, badge:"", isNew:false, tags:["Coconut Oil","Organic","Cooking"], desc:"Pure cold-pressed virgin coconut oil from Kurunegala farms. Ideal for cooking, skin care, and traditional recipes.", delivery:"Island-wide: 2–4 days" },
  { id:15, name:"Organic Jaggery – 500g", cat:"grocery", emoji:"🍯", image:"../assets/images/Organic Jaggery.webp", company:"Kithul Naturals", price:380, oldPrice:null, rating:4.6, reviews:123, badge:"new", isNew:true, tags:["Jaggery","Organic","Kithul"], desc:"Traditional kithul jaggery made from wild kithul palms in Sri Lanka's rainforest belt. 100% natural, no additives.", delivery:"Island-wide: 2–3 days" },
  { id:16, name:"Spice Mix Bundle – 6 Varieties", cat:"grocery", emoji:"🌶️", image:"../assets/images/Spice Mix Bundle – 6 Varieties.webp", company:"Matara Spice House", price:1200, oldPrice:1500, rating:4.8, reviews:168, badge:"hot", isNew:false, tags:["Spices","Curry","Bundle"], desc:"Authentic Sri Lankan spice collection — curry leaves, goraka, cloves, cardamom, pepper, and chili. Sourced directly from farmers.", delivery:"Island-wide: 2–4 days" },

  // ── Agriculture ──
  { id:17, name:"Organic Vegetable Seeds Pack", cat:"agriculture", emoji:"🌱", image:"../assets/images/Organic Vegetable Seeds Pack.webp", company:"GreenFarm SL", price:650, oldPrice:null, rating:4.5, reviews:89, badge:"new", isNew:true, tags:["Seeds","Organic","Vegetables"], desc:"Mixed vegetable seed collection suitable for Sri Lankan climate. Includes tomato, capsicum, bean, and bitter gourd varieties.", delivery:"Island-wide: 2–4 days" },
  { id:18, name:"Drip Irrigation Starter Kit", cat:"agriculture", emoji:"💧", image:"../assets/images/Drip Irrigation Starter Kit.jpg", company:"AgroTech Lanka", price:8500, oldPrice:11000, rating:4.7, reviews:41, badge:"sale", isNew:false, tags:["Irrigation","Drip","Farm"], desc:"Complete drip irrigation system for home gardens and small farms. Water-efficient and easy to set up across all soil types.", delivery:"Island-wide: 3–5 days" },
  { id:19, name:"Organic Fertilizer 25kg Bag", cat:"agriculture", emoji:"🌿", image:"../assets/images/Organic Fertilizer 25kg Bag.webp", company:"NatureFarm Supplies", price:2200, oldPrice:null, rating:4.4, reviews:67, badge:"", isNew:false, tags:["Fertilizer","Organic","Soil"], desc:"Certified organic compost fertilizer. Improves soil quality and crop yield — approved by Sri Lanka's Department of Agriculture.", delivery:"Island-wide: 3–5 days" },
  { id:20, name:"Rubber Tapper's Tool Set", cat:"agriculture", emoji:"🔧", image:"../assets/images/Rubber Tapper's Tool Set.avif", company:"Kegalle Rubber Co.", price:3400, oldPrice:null, rating:4.3, reviews:28, badge:"", isNew:false, tags:["Rubber","Tools","Agriculture"], desc:"Professional rubber tapping knives and collection cups from Kegalle's rubber industry leaders. Built for durability.", delivery:"Island-wide: 3–5 days" },

  // ── Construction ──
  { id:21, name:"Cement – 50kg Premium Bag", cat:"construction", emoji:"🧱", image:"https://images.unsplash.com/photo-1581092162562-40038e57c2dd?auto=format&fit=crop&w=600&q=80", company:"Lanka Cement PVT", price:1750, oldPrice:2000, rating:4.6, reviews:95, badge:"hot", isNew:false, tags:["Cement","Building","Construction"], desc:"High-grade Portland cement for residential and commercial construction. Bulk pricing available for contractors.", delivery:"Delivery by negotiation · Colombo & suburbs" },
  { id:22, name:"Steel Rebar – 12mm (per bundle)", cat:"construction", emoji:"🔩", image:"https://images.unsplash.com/photo-1578926314433-c6f7f1af2650?auto=format&fit=crop&w=600&q=80", company:"SteelMaster SL", price:42000, oldPrice:null, rating:4.4, reviews:31, badge:"", isNew:false, tags:["Steel","Rebar","Structural"], desc:"Grade 60 deformed steel bars for reinforced concrete construction. Meets Sri Lanka Standards Institution (SLSI) specifications.", delivery:"Colombo & Western Province" },
  { id:23, name:"Roof Sheet Aluminum – 10ft", cat:"construction", emoji:"🏚️", image:"https://images.unsplash.com/photo-1493857671505-72967e2e2760?auto=format&fit=crop&w=600&q=80", company:"MetalRoof Lanka", price:3200, oldPrice:3800, rating:4.5, reviews:48, badge:"sale", isNew:false, tags:["Roofing","Aluminum","Sheet"], desc:"Corrugated aluminum roofing sheets. Rust-proof, heat-resistant, ideal for all Sri Lankan weather conditions.", delivery:"Island-wide: 4–7 days" },

  // ── Health ──
  { id:24, name:"Herbal Ayurvedic Oil – 100ml", cat:"health", emoji:"🌿", image:"../assets/images/Herbal Ayurvedic Oil – 100ml.webp", company:"Siddhalepa Wellness", price:780, oldPrice:null, rating:4.8, reviews:245, badge:"hot", isNew:false, tags:["Ayurvedic","Herbal","Oil"], desc:"Traditional Sri Lankan ayurvedic massage oil made from 24 medicinal herbs. Relieves body aches and promotes wellbeing.", delivery:"Island-wide: 2–3 days" },
  { id:25, name:"Digital Blood Pressure Monitor", cat:"health", emoji:"💊", image:"../assets/images/Digital Blood Pressure Monitor.webp", company:"MedPlus Lanka", price:6500, oldPrice:8000, rating:4.7, reviews:112, badge:"sale", isNew:false, tags:["Medical","BP","Digital"], desc:"Clinically accurate blood pressure monitor. Easy to use for home monitoring — endorsed by Sri Lankan health professionals.", delivery:"Island-wide: 2–3 days" },
  { id:26, name:"Moringa Leaf Powder – 250g", cat:"health", emoji:"🌱", image:"../assets/images/Moringa Leaf Powder – 250g.webp", company:"Superfood SL", price:890, oldPrice:null, rating:4.6, reviews:88, badge:"new", isNew:true, tags:["Moringa","Superfood","Organic"], desc:"Pure dried moringa leaf powder from certified organic farms. Rich in iron, vitamins A, C, and K — a Sri Lankan superfood.", delivery:"Island-wide: 2–3 days" },

  // ── Office Supplies ──
  { id:27, name:"Ergonomic Office Chair", cat:"office", emoji:"🪑", image:"../assets/images/Ergonomic Office Chair.webp", company:"WorkSpace Lanka", price:18500, oldPrice:24000, rating:4.6, reviews:73, badge:"sale", isNew:false, tags:["Chair","Ergonomic","Office"], desc:"Lumbar-support office chair with adjustable height and armrests. Designed for long working hours — ideal for BizLink users.", delivery:"Colombo: 2–3 days · Island: 5–7 days" },
  { id:28, name:"A4 Printing Paper – 5 Ream Box", cat:"office", emoji:"📄", image:"../assets/images/A4 Printing Paper – 5 Ream Box.webp", company:"PaperPlus SL", price:4200, oldPrice:null, rating:4.3, reviews:156, badge:"", isNew:false, tags:["Paper","A4","Stationery"], desc:"High-brightness 80gsm A4 paper. Suitable for laser and inkjet printers. Bulk supply for offices and businesses.", delivery:"Island-wide: 2–4 days" },
  { id:29, name:"Brother Printer Ink Set (4-colour)", cat:"office", emoji:"🖨️", image:"../assets/images/Brother Printer Ink Set (4-colour).webp", company:"PrintTech Lanka", price:3800, oldPrice:4500, rating:4.5, reviews:44, badge:"", isNew:false, tags:["Ink","Printer","Brother"], desc:"Original Brother printer ink cartridge set — Black, Cyan, Magenta, Yellow. Compatible with popular Brother MFC series.", delivery:"Island-wide: 2–3 days" },

  // ── Industrial ──
  { id:30, name:"Industrial Safety Gloves (12 pairs)", cat:"industrial", emoji:"🧤", image:"https://images.unsplash.com/photo-1530124566582-a618bc2615dc?auto=format&fit=crop&w=600&q=80", company:"SafeWork Lanka", price:1800, oldPrice:null, rating:4.4, reviews:38, badge:"", isNew:false, tags:["Safety","Gloves","Industrial"], desc:"Heavy-duty cut-resistant gloves for factory, construction, and industrial workers. Meets international safety standards.", delivery:"Island-wide: 3–5 days" },
  { id:31, name:"Electric Power Drill 800W", cat:"industrial", emoji:"🔨", image:"https://images.unsplash.com/photo-1558618666-fcd25c85cd64?auto=format&fit=crop&w=600&q=80", company:"ProTools Lanka", price:14500, oldPrice:17500, rating:4.7, reviews:62, badge:"sale", isNew:false, tags:["Drill","Power Tool","Industrial"], desc:"Professional 800W electric drill for heavy-duty use. Ideal for construction sites, workshops, and industrial applications.", delivery:"Island-wide: 2–4 days" },
  { id:32, name:"Industrial Fan 36\" Heavy Duty", cat:"industrial", emoji:"💨", image:"https://images.unsplash.com/photo-1585576912902-e93b9f173f3f?auto=format&fit=crop&w=600&q=80", company:"CoolAir Industrial", price:22000, oldPrice:null, rating:4.3, reviews:19, badge:"", isNew:false, tags:["Fan","Cooling","Industrial"], desc:"Large industrial pedestal fan for warehouses, factories, and large workshops. Energy-efficient motor with 3-speed control.", delivery:"Colombo & suburbs: 3–5 days" },

  // ── Packaging ──
  { id:33, name:"Kraft Paper Bags (100 pcs)", cat:"packaging", emoji:"🛍️", image:"https://images.unsplash.com/photo-1565521409834-b0b767f0ae0c?auto=format&fit=crop&w=600&q=80", company:"EcoPack Lanka", price:1600, oldPrice:null, rating:4.5, reviews:87, badge:"new", isNew:true, tags:["Bags","Kraft","Eco"], desc:"Eco-friendly kraft paper bags for retail shops, bakeries, and restaurants. Customizable with your brand name — MOQ 100.", delivery:"Island-wide: 2–3 days" },
  { id:34, name:"Bubble Wrap Roll 50m", cat:"packaging", emoji:"📦", image:"https://images.unsplash.com/photo-1585254725176-bed249b84d83?auto=format&fit=crop&w=600&q=80", company:"PackSecure SL", price:2400, oldPrice:3000, rating:4.4, reviews:41, badge:"sale", isNew:false, tags:["Bubble Wrap","Packaging","Shipping"], desc:"Heavy-duty bubble wrap roll for safe product shipping. Ideal for electronics, ceramics, and fragile export goods.", delivery:"Island-wide: 2–4 days" },

  // ── IT Services ──
  { id:35, name:"Website Design & Development", cat:"it", emoji:"🌐", image:"https://images.unsplash.com/photo-1499334794326-6d5ee46dad65?auto=format&fit=crop&w=600&q=80", company:"PixelStudio Lanka", price:45000, oldPrice:60000, rating:4.9, reviews:76, badge:"hot", isNew:false, tags:["Web","Design","Service"], desc:"Professional website design for Sri Lankan SMEs. Mobile-responsive, SEO-ready, and integrated with BizLink CRM. Starting from Rs. 45,000.", delivery:"Service · Timeline: 2–4 weeks", isService:true },
  { id:36, name:"Cloud Hosting – 1 Year Business Plan", cat:"it", emoji:"☁️", image:"https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=600&q=80", company:"CloudHost SL", price:18000, oldPrice:24000, rating:4.6, reviews:52, badge:"sale", isNew:false, tags:["Hosting","Cloud","SSD"], desc:"Reliable cloud web hosting with 99.9% uptime SLA. Includes free SSL, 10GB SSD, and dedicated Sri Lankan support.", delivery:"Service · Activation: Same day", isService:true },

  // ── Marketing ──
  { id:37, name:"Social Media Management – Monthly", cat:"marketing", emoji:"📣", image:"https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=600&q=80", company:"Digital Compass SL", price:25000, oldPrice:null, rating:4.7, reviews:34, badge:"", isNew:true, tags:["Social Media","Facebook","Marketing"], desc:"Full social media management covering Facebook, Instagram, and WhatsApp Business. Content creation and monthly reports included.", delivery:"Service · Remote", isService:true },
  { id:38, name:"Branded Flyer Design (10 designs)", cat:"marketing", emoji:"🎨", image:"https://images.unsplash.com/photo-1561070791-2526d30994b5?auto=format&fit=crop&w=600&q=80", company:"CraftPrint Lanka", price:8500, oldPrice:12000, rating:4.5, reviews:48, badge:"sale", isNew:false, tags:["Graphic Design","Flyer","Branding"], desc:"Professional graphic design for business flyers, menus, and promotional materials. Print-ready files delivered digitally.", delivery:"Service · Delivery: 3–5 business days", isService:true },

  // ── Accounting ──
  { id:39, name:"Monthly Bookkeeping Service", cat:"accounting", emoji:"📊", image:"https://images.unsplash.com/photo-1554224311-beee415c15ac?auto=format&fit=crop&w=600&q=80", company:"AccuCount Lanka", price:12000, oldPrice:null, rating:4.8, reviews:41, badge:"new", isNew:true, tags:["Bookkeeping","Accounting","Monthly"], desc:"Full monthly bookkeeping and financial reporting for small businesses. Reconciled accounts, tax-ready statements, BizLink integrated.", delivery:"Service · Remote · Island-wide", isService:true },
  { id:40, name:"Annual Tax Filing & Compliance", cat:"accounting", emoji:"📋", image:"https://images.unsplash.com/photo-1590080876614-02539079422d?auto=format&fit=crop&w=600&q=80", company:"TaxPro Lanka", price:35000, oldPrice:42000, rating:4.7, reviews:27, badge:"sale", isNew:false, tags:["Tax","Compliance","IRD"], desc:"Complete annual tax return preparation and IRD compliance for Sri Lankan businesses. Includes VAT filing and business registration support.", delivery:"Service · Island-wide", isService:true },

  // ── Logistics ──
  { id:41, name:"Island-Wide Courier – Per Shipment", cat:"logistics", emoji:"🚚", image:"https://images.unsplash.com/photo-1578747323512-1ed1f1dc0c30?auto=format&fit=crop&w=600&q=80", company:"SwiftMove SL", price:350, oldPrice:null, rating:4.6, reviews:312, badge:"hot", isNew:false, tags:["Courier","Delivery","Island-wide"], desc:"Reliable door-to-door island-wide delivery service. Track your shipment in real time through BizLink's partner portal.", delivery:"Service · 2–4 business days", isService:true },
  { id:42, name:"Cold Chain Logistics – Perishables", cat:"logistics", emoji:"❄️", image:"https://images.unsplash.com/photo-1584183645094-487e3c21a5dc?auto=format&fit=crop&w=600&q=80", company:"CoolChain Lanka", price:1800, oldPrice:null, rating:4.4, reviews:18, badge:"new", isNew:true, tags:["Cold Chain","Perishable","Food"], desc:"Temperature-controlled delivery for food products, dairy, and pharmaceuticals. Serving Colombo and Western Province.", delivery:"Service · Colombo & suburbs", isService:true },

  // ── Extra products to round out ──
  { id:43, name:"Paddy Thresher Machine – Small", cat:"agriculture", emoji:"🌾", image:"https://images.unsplash.com/photo-1556766833-2a5c82a6b0a6?auto=format&fit=crop&w=600&q=80", company:"AgroMech Lanka", price:95000, oldPrice:110000, rating:4.5, reviews:15, badge:"", isNew:false, tags:["Machine","Paddy","Farm"], desc:"Compact paddy threshing machine for small-scale rice farmers. Reduces harvest time by 80%. Spare parts available island-wide.", delivery:"Island-wide: 5–10 days · Assembly included" },
  { id:44, name:"Coir Rope – 100m Roll", cat:"agriculture", emoji:"🌴", image:"https://images.unsplash.com/photo-1531693022064-7fb39b97db97?auto=format&fit=crop&w=600&q=80", company:"Southern Coir Co.", price:1100, oldPrice:null, rating:4.3, reviews:44, badge:"", isNew:false, tags:["Coir","Rope","Natural"], desc:"Natural coir rope made from Sri Lanka's finest coconut husk. Used in agriculture, construction, and traditional crafts.", delivery:"Island-wide: 2–4 days" },
  { id:45, name:"Floor Tiles – Marble Finish 60x60cm", cat:"construction", emoji:"🏗️", image:"https://images.unsplash.com/photo-1578926314433-c6f7f1af2650?auto=format&fit=crop&w=600&q=80", company:"Ceramic Lanka", price:480, oldPrice:null, rating:4.4, reviews:61, badge:"", isNew:false, tags:["Tiles","Floor","Marble"], desc:"Premium marble-finish porcelain tiles. Price per tile. Minimum order 50 tiles. Ideal for residential and commercial projects.", delivery:"Colombo: 3–5 days · Island: 7–10 days" },
  { id:46, name:"Casio Scientific Calculator", cat:"office", emoji:"🔢", image:"https://images.unsplash.com/photo-1611273426858-450d8e3c9fce?auto=format&fit=crop&w=600&q=80", company:"EduSupplies SL", price:2200, oldPrice:2800, rating:4.7, reviews:132, badge:"", isNew:false, tags:["Calculator","Casio","Education"], desc:"Casio FX-991ES Plus scientific calculator. Required for A/L exams and professional accounting work. Authentic with warranty.", delivery:"Island-wide: 2–3 days" },
  { id:47, name:"Stainless Steel Water Bottle 1L", cat:"health", emoji:"💧", image:"https://images.unsplash.com/photo-1602143407151-7e536f085b97?auto=format&fit=crop&w=600&q=80", company:"PureLife Lanka", price:1450, oldPrice:null, rating:4.5, reviews:95, badge:"new", isNew:true, tags:["Water Bottle","Steel","Eco"], desc:"BPA-free double-walled stainless steel water bottle. Keeps beverages cold for 24h, hot for 12h. Eco-friendly alternative to plastic.", delivery:"Island-wide: 2–3 days" },
  { id:48, name:"Jumbo Cardboard Box (10 pcs)", cat:"packaging", emoji:"📦", image:"https://images.unsplash.com/photo-1515811340770-24477517c5f2?auto=format&fit=crop&w=600&q=80", company:"BoxCraft Lanka", price:1900, oldPrice:null, rating:4.2, reviews:29, badge:"", isNew:false, tags:["Cardboard","Box","Shipping"], desc:"Heavy-duty jumbo corrugated cardboard boxes. Ideal for e-commerce shipping and warehouse storage. Custom sizes available.", delivery:"Island-wide: 2–3 days", isApiBacked:false },
];

/*STATE*/
let state = {
  cat: 'all',
  search: '',
  sort: 'featured',
  view: 'grid',
  priceMin: null,
  priceMax: null,
  minRating: 0,
  cart: [],
  wishlist: [],
  page: 1,
  itemsPerPage: 12,
  preferenceCounts: {},
};

function computeCategoryPreferences(orders, products) {
  if (!Array.isArray(orders) || orders.length === 0) {
    return {};
  }
  const productCategoryByName = {};
  products.forEach((product) => {
    if (product.name) {
      productCategoryByName[product.name.toLowerCase()] = product.cat;
    }
  });

  const counts = {};
  orders
    .forEach((order) => {
      const byProduct = productCategoryByName[String(order.product_name || '').toLowerCase()];
      const byVendor = toCategorySlug(order.vendor_category || '');
      const category = byProduct || byVendor || 'other';
      counts[category] = (counts[category] || 0) + 1;
    });

  return counts;
}

function hasPersonalization() {
  return Object.keys(state.preferenceCounts || {}).length > 0;
}

const CATEGORY_ALIASES = {
  electronics: 'electronics',
  fashion: 'fashion',
  home: 'home',
  grocery: 'grocery',
  agriculture: 'agriculture',
  construction: 'construction',
  health: 'health',
  office: 'office',
  industrial: 'industrial',
  packaging: 'packaging',
  it: 'it',
  marketing: 'marketing',
  accounting: 'accounting',
  logistics: 'logistics'
};

const CATEGORY_IMAGE_FALLBACKS = {
  electronics: '../assets/images/galaxybook.webp',
  fashion: '../assets/images/saree.jpg',
  home: '../assets/images/TeakWoodCoffeeTable.webp',
  grocery: '../assets/images/Ceylon Black Tea – 1kg Premium.webp',
  agriculture: '../assets/images/Organic Vegetable Seeds Pack.webp',
  construction: '../assets/images/Cement – 50kg Premium Bag.webp',
  health: '../assets/images/Herbal Ayurvedic Oil – 100ml.webp',
  office: '../assets/images/Ergonomic Office Chair.webp',
  industrial: '../assets/images/UPS.webp',
  packaging: '../assets/images/A4 Printing Paper – 5 Ream Box.webp',
  it: '../assets/images/POS-System.jpg',
  marketing: '../assets/images/sony.jpg',
  accounting: '../assets/images/solarlight.jpg',
  logistics: '../assets/images/Drip Irrigation Starter Kit.jpg',
  other: '../assets/images/galaxybook.webp'
};

function resolveApiProductImage(product, category) {
  const candidate = String(product.image || product.image_url || product.product_image || product.thumbnail || '').trim();
  if (candidate) {
    if (/^https?:\/\//i.test(candidate)) {
      return candidate;
    }
    if (candidate.startsWith('../') || candidate.startsWith('./') || candidate.startsWith('/')) {
      return candidate;
    }
    return `../assets/images/${candidate}`;
  }

  return CATEGORY_IMAGE_FALLBACKS[category] || CATEGORY_IMAGE_FALLBACKS.other;
}

function toCategorySlug(value) {
  const cleaned = (value || '').toString().trim().toLowerCase();
  if (!cleaned) return 'other';
  if (CATEGORY_ALIASES[cleaned]) return CATEGORY_ALIASES[cleaned];
  if (cleaned.includes('elect')) return 'electronics';
  if (cleaned.includes('fashion') || cleaned.includes('cloth')) return 'fashion';
  if (cleaned.includes('grocery') || cleaned.includes('food')) return 'grocery';
  if (cleaned.includes('agri') || cleaned.includes('farm')) return 'agriculture';
  if (cleaned.includes('const') || cleaned.includes('build')) return 'construction';
  if (cleaned.includes('health') || cleaned.includes('medical')) return 'health';
  if (cleaned.includes('office') || cleaned.includes('station')) return 'office';
  if (cleaned.includes('indus') || cleaned.includes('tool')) return 'industrial';
  if (cleaned.includes('pack')) return 'packaging';
  if (cleaned === 'it' || cleaned.includes('tech') || cleaned.includes('software')) return 'it';
  if (cleaned.includes('market')) return 'marketing';
  if (cleaned.includes('account') || cleaned.includes('finance')) return 'accounting';
  if (cleaned.includes('logist') || cleaned.includes('courier') || cleaned.includes('delivery')) return 'logistics';
  return cleaned.replace(/\s+/g, '-');
}

function pickEmoji(categorySlug) {
  const emojiMap = {
    electronics: '💻',
    fashion: '👗',
    home: '🏠',
    grocery: '🛒',
    agriculture: '🌾',
    construction: '🏗️',
    health: '⚕️',
    office: '🖊️',
    industrial: '⚙️',
    packaging: '📦',
    it: '💻',
    marketing: '📣',
    accounting: '📊',
    logistics: '🚚'
  };
  return emojiMap[categorySlug] || '📦';
}

function mapApiProduct(product, index) {
  const category = toCategorySlug(product.category);
  const price = Number(product.base_price || 0);
  const stock = Number(product.stock_quantity || 0);
  const rating = Math.max(3.8, Math.min(5, 4 + ((index % 10) / 10)));
  const reviews = 12 + (index * 7);
  const isNew = index < 6;
  const image = resolveApiProductImage(product, category);

  return {
    id: Number(product.product_id),
    name: product.product_name,
    cat: category,
    emoji: pickEmoji(category),
    image,
    company: product.shop_name || 'BizLink Vendor',
    price,
    oldPrice: null,
    rating: Number(rating.toFixed(1)),
    reviews,
    badge: isNew ? 'new' : '',
    isNew,
    tags: [category, stock > 0 ? 'In Stock' : 'Pre-order'],
    desc: String(product.product_description || '').trim() || `${product.product_name} by ${product.shop_name || 'a verified vendor'} on BizLink Marketplace.`,
    delivery: 'Island-wide: 2–5 days',
    isService: false,
    isApiBacked: true
  };
}

function updateMarketplaceCounts(products, categories) {
  const uniqueVendors = new Set(products.map((p) => p.company)).size;

  const heroCounters = document.querySelectorAll('.hs-num');
  if (heroCounters[0]) heroCounters[0].setAttribute('data-target', String(products.length));
  if (heroCounters[1]) heroCounters[1].setAttribute('data-target', String(uniqueVendors));
  if (heroCounters[2]) heroCounters[2].setAttribute('data-target', String(Math.max(1, categories.length || 0)));

  const countByCategory = {};
  products.forEach((p) => {
    countByCategory[p.cat] = (countByCategory[p.cat] || 0) + 1;
  });

  document.querySelectorAll('.cat-item[data-cat]').forEach((btn) => {
    const cat = btn.getAttribute('data-cat');
    const countEl = btn.querySelector('.cat-count');
    if (!countEl) return;
    if (cat === 'all') {
      countEl.textContent = String(products.length);
    } else {
      countEl.textContent = String(countByCategory[cat] || 0);
    }
  });
}

async function loadMarketplaceData() {
  try {
    console.log('[Marketplace] Starting API data load...');
    const [apiProducts, apiCategories, apiOrders] = await Promise.all([
      getMarketplacePublicList('get_products.php'),
      getMarketplacePublicList('get_categories.php'),
      getMarketplaceOptionalList('get_orders.php')
    ]);

    console.log(`[Marketplace] API Results - Products: ${apiProducts?.length || 0}, Categories: ${apiCategories?.length || 0}, Orders: ${apiOrders?.length || 0}`);

    if (apiProducts && apiProducts.length > 0) {
      console.log('[Marketplace] Replacing static products with API data');
      const mapped = apiProducts.map(mapApiProduct);
      PRODUCTS.splice(0, PRODUCTS.length, ...mapped);

      state.preferenceCounts = computeCategoryPreferences(
        apiOrders || [],
        mapped
      );

      updateMarketplaceCounts(mapped, apiCategories || []);
      console.log('[Marketplace] API data loaded successfully!');
      return true;
    }
  } catch (error) {
    console.error('[Marketplace] Failed to load marketplace data from API:', error);
  }

  // If API is not available, hide static demo catalog to avoid non-purchasable items.
  PRODUCTS.splice(0, PRODUCTS.length);
  updateMarketplaceCounts([], []);
  return false;
}

async function fetchJsonWithTimeout(url, timeoutMs = 3500) {
  const controller = new AbortController();
  const timeoutId = window.setTimeout(() => controller.abort(), timeoutMs);

  try {
    const response = await fetch(url, { signal: controller.signal });
    return await response.json();
  } finally {
    window.clearTimeout(timeoutId);
  }
}

async function getMarketplacePublicList(endpoint) {
  try {
    if (typeof API_BASE === 'string') {
      console.log(`[Marketplace] Fetching from API: ${API_BASE}${endpoint}`);
      const payload = await fetchJsonWithTimeout(API_BASE + endpoint);
      
      // Log the full response for debugging
      console.log(`[Marketplace] API Response for ${endpoint}:`, payload);
      
      if (payload && payload.success) {
        const dataLength = Array.isArray(payload.data) ? payload.data.length : 0;
        console.log(`[Marketplace] Successfully loaded ${dataLength} items from ${endpoint}`);
        return Array.isArray(payload.data) ? payload.data : [];
      } else if (payload) {
        console.warn(`[Marketplace] API returned success=false for ${endpoint}:`, payload.message);
      } else {
        console.warn(`[Marketplace] API returned invalid response for ${endpoint}`);
      }
    }
  } catch (error) {
    if (error && error.name === 'AbortError') {
      console.debug(`[Marketplace] API timeout for ${endpoint}`);
    } else {
      console.warn(`[Marketplace] API read failed for ${endpoint}:`, error?.message || error);
    }
  }

  return [];
}

async function getMarketplaceOptionalList(endpoint) {
  const list = await getMarketplacePublicList(endpoint);
  return Array.isArray(list) ? list : [];
}

/*MARKETPLACE STATE*/
let marketplaceState = {
  currentCustomer: null,
  isLoggedIn: false
};

/*CHECK CUSTOMER LOGIN STATUS*/
async function checkCustomerLogin() {
  try {
    if (typeof authMe !== 'function') {
      return;
    }

    const identity = await authMe(false);
    if (identity && identity.user && String(identity.user.role || '').toLowerCase() === 'customer') {
      marketplaceState.currentCustomer = identity.user;
      marketplaceState.isLoggedIn = true;
      updateNavbarForLoggedInCustomer(identity.user);
      return;
    }
  } catch (error) {
    console.debug('[Marketplace] Customer login check failed:', error);
  }

  marketplaceState.isLoggedIn = false;
  marketplaceState.currentCustomer = null;
}

async function requireCustomerForPurchase() {
  await checkCustomerLogin();

  if (marketplaceState.isLoggedIn) {
    return true;
  }

  window.alert('Please sign up or sign in as a customer to buy products.');
  window.location.href = '../pages/index.html?reason=unauthorized';
  return false;
}

/*UPDATE NAVBAR WITH CUSTOMER INFO*/
function updateNavbarForLoggedInCustomer(user) {
  const navSignIn = document.querySelector('.nav-signin');
  if (!navSignIn) return;

  const firstName = String(user.full_name || user.name || 'Customer').split(' ')[0];
  const initials = firstName.slice(0, 1).toUpperCase();

  navSignIn.innerHTML = `
    <span style="display:inline-flex;align-items:center;gap:8px;">
      <span style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg, #50C878, #137a45);color:white;font-size:0.9rem;font-weight:700;">${initials}</span>
      <span style="font-size:0.9rem;">${firstName}</span>
    </span>
  `;
  navSignIn.href = '../customer/dashboard.html';
  navSignIn.title = 'Go to dashboard';
  navSignIn.style.cursor = 'pointer';
}

function initCheckoutGuard() {
  const checkoutBtn = document.querySelector('.checkout-btn');
  if (!checkoutBtn) return;

  checkoutBtn.addEventListener('click', async (event) => {
    event.preventDefault();
    const allowed = await requireCustomerForPurchase();
    if (!allowed) {
      return;
    }

    window.alert('Checkout will be available after cart API integration.');
  });
}

/*INIT*/
document.addEventListener('DOMContentLoaded', async () => {
  // Load products first so users only see DB-backed marketplace items.
  const grid = document.getElementById('productGrid');
  if (grid) {
    grid.innerHTML = '<div style="padding:20px;color:#6b7280;grid-column:1 / -1;text-align:center;">Loading marketplace products...</div>';
  }

  initNavScroll();
  initBackToTop();

  // Check if customer is logged in
  await checkCustomerLogin();
  initCheckoutGuard();

  loadMarketplaceData().then(() => {
    renderProducts();
    animateCounters();
  });
});

/*NAVBAR SCROLL*/
function initNavScroll() {
  const nav = document.getElementById('mpNav');
  window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 60);
    document.getElementById('backToTop').classList.toggle('show', window.scrollY > 400);
  });
}

function initBackToTop() {}

/*COUNTER ANIMATION*/
function animateCounters() {
  const counters = document.querySelectorAll('.hs-num');
  const obs = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (!e.isIntersecting) return;
      const target = parseInt(e.target.dataset.target);
      let cur = 0;
      const step = target / 60;
      const tick = () => {
        cur = Math.min(cur + step, target);
        e.target.textContent = Math.floor(cur).toLocaleString();
        if (cur < target) requestAnimationFrame(tick);
      };
      tick();
      obs.unobserve(e.target);
    });
  }, { threshold: 0.5 });
  counters.forEach(c => obs.observe(c));
}

/*CATEGORY SELECTION*/
function selectCategory(cat, el) {
  state.cat = cat;
  state.page = 1;

  // Sidebar items
  document.querySelectorAll('.cat-item').forEach(b => b.classList.remove('active'));
  const sidebarItem = document.querySelector(`.cat-item[data-cat="${cat}"]`);
  if (sidebarItem) sidebarItem.classList.add('active');

  // Chips
  document.querySelectorAll('.cat-chip').forEach(c => c.classList.remove('active'));
  const chip = document.querySelector(`.cat-chip[data-cat="${cat}"]`);
  if (chip) chip.classList.add('active');

  // Breadcrumb
  const catNames = {
    all:'All Products', electronics:'Electronics', fashion:'Fashion', home:'Home',
    grocery:'Grocery', agriculture:'Agriculture', construction:'Construction', health:'Health',
    office:'Office Supplies', industrial:'Industrial', packaging:'Packaging',
    it:'IT Services', marketing:'Marketing', accounting:'Accounting', logistics:'Logistics'
  };
  document.getElementById('bcCat').textContent = catNames[cat] || cat;

  renderProducts();
  updateActiveFilters();
}

/*SEARCH*/
function handleSearch(val) {
  state.search = val.trim().toLowerCase();
  state.page = 1;
  document.getElementById('searchClear').classList.toggle('visible', val.length > 0);
  renderProducts();
  updateActiveFilters();
}

function clearSearch() {
  document.getElementById('globalSearch').value = '';
  state.search = '';
  document.getElementById('searchClear').classList.remove('visible');
  renderProducts();
  updateActiveFilters();
}

/*SORT*/
function sortProducts(val) {
  state.sort = val;
  renderProducts();
}

/*VIEW*/
function setView(v) {
  state.view = v;
  const grid = document.getElementById('productGrid');
  grid.classList.toggle('list-view', v === 'list');
  document.getElementById('gridViewBtn').classList.toggle('active', v === 'grid');
  document.getElementById('listViewBtn').classList.toggle('active', v === 'list');
}

/*PRICE / RATING FILTER*/
function applyPriceFilter() {
  state.priceMin = parseFloat(document.getElementById('priceMin').value) || null;
  state.priceMax = parseFloat(document.getElementById('priceMax').value) || null;
  state.page = 1;
  renderProducts();
  updateActiveFilters();
}

function setRatingFilter(r, el) {
  state.minRating = parseFloat(r);
  document.querySelectorAll('.sf-btn').forEach(b => b.classList.remove('active'));
  el.classList.add('active');
  state.page = 1;
  renderProducts();
  updateActiveFilters();
}

/*SIDEBAR (mobile)*/
function toggleSidebar() {
  document.getElementById('catSidebar').classList.toggle('open');
}
function closeSidebar() {
  document.getElementById('catSidebar').classList.remove('open');
}

/*CATEGORY SEARCH (sidebar filter)*/
function filterCategories(val) {
  const v = val.toLowerCase();
  document.querySelectorAll('.cat-item').forEach(item => {
    const name = item.querySelector('.cat-name')?.textContent?.toLowerCase() || '';
    item.style.display = name.includes(v) ? '' : 'none';
  });
}

/*ACTIVE FILTERS DISPLAY*/
function updateActiveFilters() {
  const container = document.getElementById('activeFilters');
  container.innerHTML = '';
  if (state.cat !== 'all') addFilterTag(container, `Category: ${state.cat}`, () => selectCategory('all', null));
  if (state.search) addFilterTag(container, `Search: "${state.search}"`, clearSearch);
  if (state.priceMin) addFilterTag(container, `Min: Rs.${state.priceMin.toLocaleString()}`, () => { document.getElementById('priceMin').value=''; state.priceMin=null; renderProducts(); updateActiveFilters(); });
  if (state.priceMax) addFilterTag(container, `Max: Rs.${state.priceMax.toLocaleString()}`, () => { document.getElementById('priceMax').value=''; state.priceMax=null; renderProducts(); updateActiveFilters(); });
  if (state.minRating > 0) addFilterTag(container, `Rating: ${state.minRating}★+`, () => setRatingFilter(0, document.querySelector('.sf-btn')));
}

function addFilterTag(container, label, removeFn) {
  const tag = document.createElement('div');
  tag.className = 'filter-tag';
  tag.innerHTML = `${label}<button class="filter-tag-remove" onclick="(${removeFn.toString()})()">✕</button>`;
  container.appendChild(tag);
}

/*FILTER & SORT PRODUCTS*/
function getFilteredProducts() {
  let list = [...PRODUCTS];

  if (state.cat !== 'all') list = list.filter(p => p.cat === state.cat);
  if (state.search) list = list.filter(p =>
    p.name.toLowerCase().includes(state.search) ||
    p.company.toLowerCase().includes(state.search) ||
    p.tags.some(t => t.toLowerCase().includes(state.search))
  );
  if (state.priceMin !== null) list = list.filter(p => p.price >= state.priceMin);
  if (state.priceMax !== null) list = list.filter(p => p.price <= state.priceMax);
  if (state.minRating > 0) list = list.filter(p => p.rating >= state.minRating);

  switch (state.sort) {
    case 'price-asc':  list.sort((a,b) => a.price - b.price); break;
    case 'price-desc': list.sort((a,b) => b.price - a.price); break;
    case 'rating':     list.sort((a,b) => b.rating - a.rating); break;
    case 'newest':     list.sort((a,b) => (b.isNew ? 1 : 0) - (a.isNew ? 1 : 0)); break;
    default:
      if (hasPersonalization()) {
        list = list
          .map((product, index) => ({
            product,
            index,
            score: state.preferenceCounts[product.cat] || 0
          }))
          .sort((a, b) => {
            if (b.score !== a.score) return b.score - a.score;
            if (b.product.rating !== a.product.rating) return b.product.rating - a.product.rating;
            return a.index - b.index;
          })
          .map((entry) => entry.product);
      }
      break;
  }

  return list;
}

/*RENDER PRODUCTS*/
function renderProducts() {
  const filtered = getFilteredProducts();
  const total = filtered.length;
  const totalPages = Math.ceil(total / state.itemsPerPage);
  const start = (state.page - 1) * state.itemsPerPage;
  const pageItems = filtered.slice(start, start + state.itemsPerPage);

  const grid = document.getElementById('productGrid');
  const empty = document.getElementById('emptyState');

  const personalizedSuffix = hasPersonalization() && state.sort === 'featured' ? ' • Personalized for you' : '';
  document.getElementById('resultCount').textContent = `${total} product${total !== 1 ? 's' : ''} found${personalizedSuffix}`;

  if (pageItems.length === 0) {
    grid.innerHTML = '';
    empty.classList.remove('hidden');
  } else {
    empty.classList.add('hidden');
    grid.innerHTML = pageItems.map((p, i) => renderCard(p, i)).join('');
  }

  renderPagination(totalPages);
}

/*RENDER CARD*/
function renderCard(p, i) {
  const isWished = state.wishlist.includes(p.id);
  const inCart = state.cart.some(c => c.id === p.id);
  const stars = renderStars(p.rating);
  const delay = (i % 12) * 0.05;
  const badgeHtml = p.badge ? `<div class="card-badge badge-${p.badge}">${p.badge.toUpperCase()}</div>` : '';
  const newBadge = p.isNew && !p.badge ? `<div class="card-badge badge-new">NEW</div>` : '';
  const serviceBadge = p.isService ? `<div class="card-badge badge-service">SERVICE</div>` : '';

  const addBtnClass = p.isService ? 'service-btn' : (!marketplaceState.isLoggedIn ? 'guest-btn' : (inCart ? 'added' : ''));
  const addBtnText = p.isService ? '📞 Enquire' : (!marketplaceState.isLoggedIn ? 'Login to Buy' : (inCart ? '✓ Added' : '+ Cart'));
  const buyNowButtonHtml = (!p.isService && marketplaceState.isLoggedIn)
    ? `<button class="card-add-btn" onclick="handleBuyNow(event, ${p.id})">Buy Now</button>`
    : '';

  const oldPriceHtml = p.oldPrice ? `<div class="card-price-old">Rs. ${p.oldPrice.toLocaleString()}</div>` : '';
  const stripeReadinessBadge = p.isService
    ? ''
    : '<span style="display:inline-block;margin-top:8px;padding:3px 10px;border-radius:999px;background:#e9fbf2;border:1px solid #9ee1be;color:#137a45;font-size:0.74rem;font-weight:700;">Stripe checkout</span>';

  return `
    <div class="prod-card" style="animation-delay:${delay}s" onclick="openModal(${p.id})">
      <div class="card-img-wrap">
        <img class="card-img" src="${p.image}" alt="${p.name}" loading="lazy" onerror="this.src='https://images.unsplash.com/photo-1611273426858-450d8e3c9fce?auto=format&fit=crop&w=600&q=80'" />
        <div class="card-img-actions">
          <button class="card-action-btn ${isWished ? 'wishlisted' : ''}"
            onclick="toggleWishlistItem(event, ${p.id})"
            title="Wishlist">
            ${isWished ? '❤️' : '🤍'}
          </button>
          <button class="card-action-btn" onclick="openModal(${p.id})" title="Quick View">👁️</button>
        </div>
        <div class="card-badge-wrap">
          ${badgeHtml}${newBadge}${serviceBadge}
        </div>
      </div>
      <div class="card-body">
        <div class="card-category">${p.cat.toUpperCase()}</div>
        <div class="card-title">${p.name}</div>
        <div class="card-company">by <span>${p.company}</span></div>
        ${stripeReadinessBadge}
        <div class="card-rating">
          <span class="stars">${stars}</span>
          <span class="rating-num">${p.rating}</span>
          <span class="review-count">(${p.reviews})</span>
        </div>
        <div class="card-price-row">
          <div class="price-wrap">
            <div class="card-price">Rs. ${p.price.toLocaleString()}</div>
            ${oldPriceHtml}
          </div>
          <div style="display:flex;gap:8px;align-items:center;">
            ${buyNowButtonHtml}
            <button class="card-add-btn ${addBtnClass}"
              onclick="handleAddToCart(event, ${p.id})">
              ${addBtnText}
            </button>
          </div>
        </div>
      </div>
    </div>
  `;
}

/*RENDER STARS*/
function renderStars(rating) {
  let s = '';
  for (let i = 1; i <= 5; i++) {
    if (rating >= i) s += '★';
    else if (rating >= i - 0.5) s += '½';
    else s += '☆';
  }
  return s;
}

/*PAGINATION*/
function renderPagination(total) {
  const pg = document.getElementById('pagination');
  if (total <= 1) { pg.innerHTML = ''; return; }

  let html = '';
  if (state.page > 1) html += `<button class="page-btn" onclick="goPage(${state.page - 1})">‹</button>`;

  for (let i = 1; i <= total; i++) {
    if (i === 1 || i === total || Math.abs(i - state.page) <= 1) {
      html += `<button class="page-btn ${i === state.page ? 'active' : ''}" onclick="goPage(${i})">${i}</button>`;
    } else if (Math.abs(i - state.page) === 2) {
      html += `<button class="page-btn dots">…</button>`;
    }
  }

  if (state.page < total) html += `<button class="page-btn" onclick="goPage(${state.page + 1})">›</button>`;
  pg.innerHTML = html;
}

function goPage(p) {
  state.page = p;
  renderProducts();
  document.getElementById('marketplace').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/* MODAL*/
function openModal(id) {
  const p = PRODUCTS.find(x => x.id === id);
  if (!p) return;

  const isWished = state.wishlist.includes(p.id);
  const stars = renderStars(p.rating);
  const badgeHtml = p.badge ? `<div class="card-badge badge-${p.badge}">${p.badge.toUpperCase()}</div>` : '';
  const newBadge = p.isNew && !p.badge ? `<div class="card-badge badge-new">NEW</div>` : '';
  const serviceBadge = p.isService ? `<div class="card-badge badge-service">SERVICE</div>` : '';
  const saveHtml = p.oldPrice ? `<span class="modal-save">Save Rs. ${(p.oldPrice - p.price).toLocaleString()}</span>` : '';
  const oldPriceHtml = p.oldPrice ? `<span class="modal-price-old">Rs. ${p.oldPrice.toLocaleString()}</span>` : '';
  const tagsHtml = p.tags.map(t => `<span class="modal-tag">${t}</span>`).join('');
  const addBtnLabel = p.isService ? '📞 Contact Vendor' : '🛒 Add to Cart';
  const stripeReadinessModal = p.isService
    ? ''
    : '<span style="display:inline-block;margin-top:10px;padding:4px 12px;border-radius:999px;background:#e9fbf2;border:1px solid #9ee1be;color:#137a45;font-size:0.76rem;font-weight:700;">Payable with Stripe (customer login required)</span>';

  document.getElementById('modalInner').innerHTML = `
    <div class="modal-img-col">
      <div class="modal-img-badge-wrap">${badgeHtml}${newBadge}${serviceBadge}</div>
      <img class="modal-img" src="${p.image}" alt="${p.name}" loading="lazy" onerror="this.src='https://images.unsplash.com/photo-1611273426858-450d8e3c9fce?auto=format&fit=crop&w=900&q=80'" />
      <div class="modal-img-actions">
        <button class="modal-act-btn" onclick="toggleWishlistItem(event, ${p.id})" title="Wishlist">
          ${isWished ? '❤️' : '🤍'}
        </button>
        <button class="modal-act-btn" title="Share">🔗</button>
      </div>
    </div>
    <div class="modal-info-col">
      <div class="modal-cat">${p.cat.toUpperCase()} ${p.isService ? '· SERVICE' : '· PRODUCT'}</div>
      <h2 class="modal-title">${p.name}</h2>
      <div class="modal-company">by <strong>${p.company}</strong></div>

      <div class="modal-rating">
        <span class="modal-stars">${stars}</span>
        <span class="modal-rating-num">${p.rating}</span>
        <span class="modal-review-cnt">(${p.reviews} reviews)</span>
      </div>

      ${stripeReadinessModal}

      <div class="modal-price-row">
        <span class="modal-price">Rs. ${p.price.toLocaleString()}</span>
        ${oldPriceHtml}
        ${saveHtml}
      </div>

      <p class="modal-desc">${p.desc}</p>

      <div class="modal-tags">${tagsHtml}</div>

      ${!p.isService ? `
      <div class="modal-qty-row">
        <span class="qty-label">Quantity:</span>
        <div class="qty-ctrl">
          <button class="qty-btn" onclick="changeQty(-1)">−</button>
          <input class="qty-val" id="modalQty" type="number" value="1" min="1" max="99" readonly/>
          <button class="qty-btn" onclick="changeQty(1)">+</button>
        </div>
      </div>
      ` : ''}

      <div class="modal-actions">
        ${(!p.isService && marketplaceState.isLoggedIn) ? `<button class="modal-add-btn" onclick="modalBuyNow(${p.id})">Pay with Stripe</button>` : ''}
        <button class="modal-add-btn" onclick="modalAddToCart(${p.id})">
          ${!marketplaceState.isLoggedIn && !p.isService ? 'Login to Buy' : addBtnLabel}
        </button>
        <button class="modal-wish-btn" onclick="toggleWishlistItem(event, ${p.id})" title="Wishlist">
          ${isWished ? '❤️' : '🤍'}
        </button>
      </div>

      <div class="modal-vendor-card">
        <div class="mvc-avatar">🏪</div>
        <div class="mvc-info">
          <strong>${p.company}</strong>
          <span>Verified BizLink Vendor · 🇱🇰</span>
        </div>
        <button class="mvc-chat">💬 Chat</button>
      </div>

      <div class="modal-delivery">
        🚚 <strong>Delivery:</strong> ${p.delivery}
      </div>
    </div>
  `;

  document.getElementById('modalOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeModal(e) {
  if (e.target === document.getElementById('modalOverlay')) closeModalDirect();
}
function closeModalDirect() {
  document.getElementById('modalOverlay').classList.remove('open');
  document.body.style.overflow = '';
}

function changeQty(delta) {
  const input = document.getElementById('modalQty');
  if (!input) return;
  const val = Math.max(1, Math.min(99, parseInt(input.value) + delta));
  input.value = val;
}

async function modalAddToCart(id) {
  const qty = parseInt(document.getElementById('modalQty')?.value || 1);
  const added = await addToCartById(id, qty);
  if (added) {
    closeModalDirect();
  }
}

async function modalBuyNow(id) {
  const qty = parseInt(document.getElementById('modalQty')?.value || 1);
  await startBuyNowCheckout(id, qty);
}

/* CART*/
async function handleAddToCart(e, id) {
  e.stopPropagation();
  await addToCartById(id, 1);
}

async function handleBuyNow(e, id) {
  e.stopPropagation();
  await startBuyNowCheckout(id, 1);
}

async function startBuyNowCheckout(id, qty = 1) {
  const p = PRODUCTS.find(x => x.id === id);
  if (!p) {
    window.alert('Product not found. Please refresh the page and try again.');
    return;
  }

  if (p.isService) {
    window.alert('This is a service listing. Please contact the vendor directly.');
    return;
  }

  const allowed = await requireCustomerForPurchase();
  if (!allowed) {
    return;
  }

  if (typeof createMarketplaceStripeCheckout !== 'function') {
    window.alert('Payment service is currently unavailable.');
    return;
  }

  const result = await createMarketplaceStripeCheckout(id, qty);
  if (!result || !result.success || !result.data || !result.data.checkout_url) {
    const msg = result && result.message ? result.message : 'Unable to start Stripe checkout.';
    window.alert(msg);
    return;
  }

  window.location.href = result.data.checkout_url;
}

async function addToCartById(id, qty = 1) {
  const p = PRODUCTS.find(x => x.id === id);
  if (!p) return false;

  if (!p.isService) {
    const allowed = await requireCustomerForPurchase();
    if (!allowed) {
      return false;
    }
  }

  const existing = state.cart.find(c => c.id === id);
  if (existing) {
    existing.qty = Math.min(existing.qty + qty, 99);
  } else {
    state.cart.push({ id, name: p.name, price: p.price, emoji: p.emoji, qty });
  }

  updateCartBadge();
  renderCartItems();
  showToast(`🛒 "${p.name}" added to cart!`, 'cart');
  // Re-render to update button state
  renderProducts();
  return true;
}

function toggleCart() {
  const overlay = document.getElementById('cartOverlay');
  overlay.classList.toggle('open');
  document.body.style.overflow = overlay.classList.contains('open') ? 'hidden' : '';
}

function closeCartOverlay(e) {
  if (e.target === document.getElementById('cartOverlay')) toggleCart();
}

function updateCartBadge() {
  const total = state.cart.reduce((a, c) => a + c.qty, 0);
  document.getElementById('cartBadge').textContent = total > 99 ? '99+' : total;
}

function renderCartItems() {
  const container = document.getElementById('cartItems');
  const footer    = document.getElementById('cartFooter');

  // Always rebuild the container from scratch so nothing gets lost
  if (state.cart.length === 0) {
    container.innerHTML = `
      <div class="cart-empty" id="cartEmpty">
        <span>🛒</span>
        <p>Your cart is empty</p>
        <p class="sin-small">ඔබේ කූඩය හිස්ය</p>
      </div>`;
    footer.style.opacity      = '0.4';
    footer.style.pointerEvents = 'none';
    document.getElementById('cartTotal').textContent = 'Rs. 0';
    return;
  }

  footer.style.opacity      = '1';
  footer.style.pointerEvents = 'all';

  let total = 0;
  const itemsHtml = state.cart.map(item => {
    total += item.price * item.qty;
    return `
      <div class="cart-item">
        <span class="ci-emoji">${item.emoji}</span>
        <div class="ci-info">
          <div class="ci-name">${item.name}</div>
          <div class="ci-price">Rs. ${(item.price * item.qty).toLocaleString()}</div>
          <div class="ci-qty">
            <button class="ci-qty-btn" onclick="changeCartQty(${item.id}, -1)">−</button>
            <span class="ci-qty-val">${item.qty}</span>
            <button class="ci-qty-btn" onclick="changeCartQty(${item.id}, 1)">+</button>
          </div>
        </div>
        <button class="ci-remove" onclick="removeFromCart(${item.id})" title="Remove">🗑️</button>
      </div>`;
  }).join('');

  container.innerHTML = itemsHtml;
  document.getElementById('cartTotal').textContent = `Rs. ${total.toLocaleString()}`;
}

function changeCartQty(id, delta) {
  const item = state.cart.find(c => c.id === id);
  if (!item) return;
  item.qty = Math.max(1, Math.min(99, item.qty + delta));
  updateCartBadge();
  renderCartItems();
}

function removeFromCart(id) {
  const p = PRODUCTS.find(x => x.id === id);
  state.cart = state.cart.filter(c => c.id !== id);
  updateCartBadge();
  renderCartItems();
  renderProducts();
  showToast(`🗑️ Removed from cart`, 'remove');
}

/*WISHLIST*/
function toggleWishlist() {}

function toggleWishlistItem(e, id) {
  e.stopPropagation();
  const p = PRODUCTS.find(x => x.id === id);
  if (state.wishlist.includes(id)) {
    state.wishlist = state.wishlist.filter(w => w !== id);
    showToast(`💔 Removed from wishlist`, 'wish');
  } else {
    state.wishlist.push(id);
    showToast(`❤️ "${p.name}" added to wishlist!`, 'wish');
  }
  document.getElementById('wishlistBadge').textContent = state.wishlist.length;
  renderProducts();
}

/*TOAST*/
function showToast(msg, type = 'info') {
  const wrap = document.getElementById('toastWrap');
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.textContent = msg;
  wrap.appendChild(toast);

  setTimeout(() => {
    toast.classList.add('toast-out');
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

/*KEYBOARD*/
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    closeModalDirect();
    if (document.getElementById('cartOverlay').classList.contains('open')) toggleCart();
  }
});

console.log('%c BizLink Marketplace 🇱🇰 ', 'background:#50C878;color:#fff;font-size:14px;padding:6px 14px;border-radius:4px;');
