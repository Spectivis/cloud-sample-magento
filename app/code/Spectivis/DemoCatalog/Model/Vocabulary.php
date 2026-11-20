<?php
/**
 * Copyright Spectivis. Licensed under OSL-3.0 and AFL-3.0, as Mage-OS.
 */
declare(strict_types=1);

namespace Spectivis\DemoCatalog\Model;

/**
 * The words the catalog is made of. Brand names are invented.
 *
 * Eight departments, six subcategories each and six leaf categories under
 * each subcategory: 344 categories. A leaf written "Plural|Singular" names
 * its products with the singular.
 */
class Vocabulary
{
    /**
     * Size ranges: apparel, kids' clothes and shoes. Other departments have
     * no size and no configurable products.
     */
    public const SIZES = [
        'apparel' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
        'kids' => ['2Y', '4Y', '6Y', '8Y', '10Y', '12Y'],
        'shoes' => ['EU 36', 'EU 37', 'EU 38', 'EU 39', 'EU 40', 'EU 41', 'EU 42', 'EU 43', 'EU 44', 'EU 45', 'EU 46'],
    ];

    public const COLORS = [
        'Black', 'White', 'Grey', 'Navy', 'Blue', 'Red', 'Green', 'Olive',
        'Beige', 'Brown', 'Pink', 'Purple', 'Yellow', 'Orange', 'Teal', 'Burgundy',
    ];

    public const PATTERNS = ['Solid', 'Striped', 'Checked', 'Floral', 'Printed', 'Camouflage', 'Herringbone', 'Colour Block'];

    public const SEASONS = ['Spring/Summer', 'Autumn/Winter', 'All Season'];

    public const BRANDS = [
        'Ashgrove', 'Bramblewick', 'Copperline', 'Driftmoor', 'Elmstead', 'Fernhollow', 'Granite Peak', 'Harbourline',
        'Ironbark Supply', 'Juniper Row', 'Kestrel & Finch', 'Larchwood', 'Mossbank', 'Northwind Goods', 'Oakhaven',
        'Pinecrest Studio', 'Quarry & Co', 'Redfern Mills', 'Saltmarsh', 'Thistledown', 'Uplander', 'Valemont',
        'Willowmere', 'Yarrowfield', 'Zephyr Lane', 'Amberly Works', 'Birchway', 'Coastline Goods', 'Dunmoor',
        'Everglen', 'Foxbury', 'Glenhaven', 'Hollowtree', 'Inkwell & Oak', 'Kinloch', 'Lumen & Loom',
        'Marlowe Street', 'Nettlefield', 'Orchard Lane', 'Pebblebrook',
    ];

    public const ADJECTIVES = [
        'Classic', 'Essential', 'Relaxed', 'Slim', 'Everyday', 'Lightweight', 'Heritage', 'Premium', 'Vintage',
        'Modern', 'Soft', 'Easy', 'Tailored', 'Rugged', 'Urban', 'Coastal', 'Alpine', 'Studio', 'Weekend', 'Signature',
    ];

    public const FEATURES = [
        'reinforced seams', 'a soft brushed finish', 'a water-repellent coating', 'recycled trims', 'a relaxed fit',
        'a slim profile', 'easy-care fabric', 'hidden pockets', 'breathable panels', 'a lifetime guarantee',
        'quick-dry fibres', 'a stain-resistant finish', 'adjustable straps', 'a padded base', 'a matte finish',
        'hand-finished details', 'contrast stitching', 'a modular design', 'non-slip grips', 'a compact fold',
    ];

    /**
     * @var array<string, array{size: ?string, configurable: float, price: array{0: int, 1: int},
     *     materials: list<string>, children: array<string, list<string>>}>
     */
    public const DEPARTMENTS = [
        'Women' => [
            'size' => 'apparel', 'configurable' => 0.12, 'price' => [15, 180],
            'materials' => ['Cotton', 'Organic Cotton', 'Linen', 'Wool', 'Merino Wool', 'Cashmere', 'Polyester', 'Recycled Polyester', 'Denim', 'Viscose'],
            'children' => [
                'Tops' => ['T-Shirts', 'Blouses', 'Tank Tops', 'Sweaters', 'Hoodies|Hoodie', 'Cardigans'],
                'Dresses' => ['Maxi Dresses', 'Midi Dresses', 'Mini Dresses', 'Wrap Dresses', 'Shirt Dresses', 'Evening Dresses'],
                'Bottoms' => ['Jeans', 'Trousers', 'Skirts', 'Shorts', 'Leggings', 'Joggers'],
                'Outerwear' => ['Jackets', 'Coats', 'Blazers', 'Parkas', 'Vests', 'Raincoats'],
                'Activewear' => ['Sports Bras', 'Yoga Pants', 'Running Tops', 'Track Jackets', 'Training Shorts', 'Base Layers'],
                'Sleep & Lounge' => ['Pajama Sets', 'Robes', 'Nightgowns', 'Lounge Pants', 'Sleep Shirts', 'Lounge Sets'],
            ],
        ],
        'Men' => [
            'size' => 'apparel', 'configurable' => 0.12, 'price' => [15, 200],
            'materials' => ['Cotton', 'Organic Cotton', 'Linen', 'Wool', 'Merino Wool', 'Polyester', 'Recycled Polyester', 'Denim', 'Corduroy', 'Flannel'],
            'children' => [
                'Tops' => ['T-Shirts', 'Polo Shirts', 'Henleys', 'Sweaters', 'Hoodies|Hoodie', 'Sweatshirts'],
                'Shirts' => ['Oxford Shirts', 'Flannel Shirts', 'Linen Shirts', 'Dress Shirts', 'Denim Shirts', 'Overshirts'],
                'Bottoms' => ['Jeans', 'Chinos', 'Cargo Pants', 'Shorts', 'Joggers', 'Dress Pants'],
                'Outerwear' => ['Jackets', 'Coats', 'Blazers', 'Parkas', 'Vests', 'Bomber Jackets'],
                'Activewear' => ['Training Tees', 'Running Shorts', 'Track Pants', 'Compression Tops', 'Base Layers', 'Gym Hoodies|Gym Hoodie'],
                'Underwear & Socks' => ['Boxer Briefs', 'Trunks', 'Undershirts', 'Crew Socks', 'Ankle Socks', 'Thermal Sets'],
            ],
        ],
        'Kids' => [
            'size' => 'kids', 'configurable' => 0.12, 'price' => [8, 70],
            'materials' => ['Cotton', 'Organic Cotton', 'Polyester', 'Recycled Polyester', 'Fleece', 'Denim', 'Bamboo'],
            'children' => [
                'Girls' => ['Dresses', 'T-Shirts', 'Leggings', 'Skirts', 'Sweatshirts', 'Pajamas|Pajama Set'],
                'Boys' => ['Shirts', 'Polo Shirts', 'Jeans', 'Shorts', 'Hoodies|Hoodie', 'Pajamas|Pajama Set'],
                'Baby' => ['Bodysuits', 'Sleepsuits', 'Rompers', 'Baby Sets', 'Bibs', 'Baby Hats'],
                'School' => ['School Polos', 'School Trousers', 'School Skirts', 'School Cardigans', 'Pinafores', 'School Bags'],
                'Kids Outerwear' => ['Raincoats', 'Puffer Jackets', 'Fleece Jackets', 'Snowsuits', 'Gilets', 'Ponchos'],
                'Kids Accessories' => ['Kids Hats', 'Kids Gloves', 'Kids Scarves|Kids Scarf', 'Lunch Bags', 'Kids Water Bottles', 'Kids Backpacks'],
            ],
        ],
        'Shoes' => [
            'size' => 'shoes', 'configurable' => 0.12, 'price' => [25, 240],
            'materials' => ['Leather', 'Suede', 'Canvas', 'Mesh', 'Recycled Polyester', 'Rubber', 'Nubuck'],
            'children' => [
                'Sneakers' => ['Running Shoes', 'Court Sneakers', 'Slip-On Sneakers', 'High-Top Sneakers', 'Trail Runners', 'Walking Shoes'],
                'Boots' => ['Chelsea Boots', 'Hiking Boots', 'Ankle Boots', 'Work Boots', 'Rain Boots', 'Snow Boots'],
                'Sandals' => ['Slides', 'Flip Flops', 'Sport Sandals', 'Espadrilles', 'Wedge Sandals', 'Strappy Sandals'],
                'Formal Shoes' => ['Oxfords|Oxford Shoes', 'Loafers', 'Brogues', 'Derby Shoes', 'Pumps', 'Ballet Flats'],
                'Kids Shoes' => ['Kids Sneakers', 'School Shoes', 'Kids Sandals', 'Kids Boots', 'First Walkers|First Walker Shoes', 'Light-Up Sneakers'],
                'Shoe Care' => ['Shoe Polish', 'Insoles', 'Shoe Trees', 'Shoelaces', 'Waterproofing Sprays', 'Shoe Brushes'],
            ],
        ],
        'Bags & Accessories' => [
            'size' => null, 'configurable' => 0.0, 'price' => [10, 320],
            'materials' => ['Leather', 'Canvas', 'Nylon', 'Recycled Polyester', 'Stainless Steel', 'Sterling Silver', 'Wool', 'Cotton'],
            'children' => [
                'Bags' => ['Backpacks', 'Tote Bags', 'Crossbody Bags', 'Duffel Bags', 'Laptop Bags', 'Wallets'],
                'Jewellery' => ['Necklaces', 'Bracelets', 'Earrings', 'Rings', 'Watches', 'Anklets'],
                'Hats & Scarves' => ['Beanies|Beanie', 'Baseball Caps', 'Sun Hats', 'Scarves|Scarf', 'Gloves', 'Bucket Hats'],
                'Small Goods' => ['Leather Belts', 'Card Holders', 'Key Rings', 'Sunglasses', 'Umbrellas', 'Phone Cases'],
                'Travel' => ['Suitcases', 'Carry-On Bags', 'Packing Cubes', 'Travel Pillows', 'Toiletry Bags', 'Luggage Tags'],
                'Tech Accessories' => ['Headphones', 'Chargers', 'Cables', 'Power Banks', 'Laptop Sleeves', 'Earbuds'],
            ],
        ],
        'Home & Living' => [
            'size' => null, 'configurable' => 0.0, 'price' => [8, 420],
            'materials' => ['Cotton', 'Linen', 'Ceramic', 'Stoneware', 'Glass', 'Oak', 'Walnut', 'Stainless Steel', 'Bamboo', 'Marble'],
            'children' => [
                'Bedding' => ['Duvet Covers', 'Sheet Sets', 'Pillowcases', 'Blankets', 'Quilts', 'Mattress Toppers'],
                'Bath' => ['Bath Towels', 'Bath Mats', 'Shower Curtains', 'Bathrobes', 'Soap Dispensers', 'Towel Racks'],
                'Kitchen' => ['Cookware Sets', 'Frying Pans', 'Knife Sets', 'Cutting Boards', 'Mixing Bowls', 'Coffee Makers'],
                'Dining' => ['Dinner Plates', 'Mugs', 'Glassware Sets', 'Cutlery Sets', 'Table Runners|Table Runner', 'Serving Bowls'],
                'Decor' => ['Cushions', 'Throws', 'Vases', 'Wall Art|Wall Art Print', 'Candles', 'Picture Frames'],
                'Lighting' => ['Table Lamps', 'Floor Lamps', 'Pendant Lights', 'String Lights', 'Desk Lamps', 'Wall Lights'],
            ],
        ],
        'Outdoor' => [
            'size' => null, 'configurable' => 0.0, 'price' => [10, 900],
            'materials' => ['Nylon', 'Ripstop Nylon', 'Polyester', 'Aluminium', 'Stainless Steel', 'Teak', 'Canvas', 'Cast Iron'],
            'children' => [
                'Camping' => ['Tents', 'Sleeping Bags', 'Camping Chairs', 'Lanterns', 'Cool Boxes', 'Camping Stoves'],
                'Hiking' => ['Hiking Backpacks', 'Trekking Poles', 'Hydration Packs', 'Headlamps', 'Hiking Socks', 'Rain Jackets'],
                'Garden' => ['Planters', 'Garden Tools|Garden Tool Set', 'Watering Cans', 'Garden Hoses', 'Outdoor Cushions', 'Bird Feeders'],
                'Outdoor Furniture' => ['Patio Chairs', 'Patio Tables', 'Loungers', 'Hammocks', 'Parasols', 'Bistro Sets'],
                'Grilling' => ['Charcoal Grills', 'Gas Grills', 'Grill Tools|Grill Tool Set', 'Grill Covers', 'Smokers', 'Pizza Ovens'],
                'Water Sports' => ['Life Jackets', 'Paddle Boards', 'Snorkel Sets', 'Wetsuits', 'Dry Bags', 'Swim Fins'],
            ],
        ],
        'Sports & Fitness' => [
            'size' => null, 'configurable' => 0.0, 'price' => [5, 520],
            'materials' => ['Rubber', 'Neoprene', 'Steel', 'Carbon Fibre', 'Aluminium', 'Polyester', 'Foam', 'Graphite'],
            'children' => [
                'Fitness Equipment' => ['Dumbbells', 'Kettlebells', 'Yoga Mats', 'Resistance Bands', 'Jump Ropes', 'Foam Rollers'],
                'Cycling' => ['Bike Helmets', 'Bike Lights', 'Cycling Gloves', 'Bike Locks', 'Water Bottles', 'Cycling Jerseys'],
                'Running' => ['Running Belts', 'GPS Watches', 'Running Caps', 'Reflective Vests', 'Running Socks', 'Armbands'],
                'Team Sports' => ['Footballs', 'Basketballs', 'Volleyballs', 'Shin Guards', 'Goalkeeper Gloves', 'Sports Bags'],
                'Racket Sports' => ['Tennis Rackets', 'Badminton Rackets', 'Padel Rackets', 'Tennis Balls', 'Shuttlecocks', 'Racket Bags'],
                'Swimming' => ['Swimsuits', 'Swim Shorts', 'Swim Goggles', 'Swim Caps', 'Kickboards', 'Towel Ponchos'],
            ],
        ],
    ];

    /**
     * Materials by subcategory, where the department's list would not fit.
     */
    public const SUB_MATERIALS = [
        'Shoe Care' => ['Natural Wax', 'Beech Wood', 'Cedar Wood', 'Cotton', 'Horsehair'],
        'Kids Accessories' => ['Cotton', 'Fleece', 'Recycled Polyester', 'Stainless Steel'],
        'Jewellery' => ['Sterling Silver', 'Gold Plated', 'Stainless Steel', 'Rose Gold Plated', 'Titanium'],
        'Tech Accessories' => ['Aluminium', 'Recycled Plastic', 'Silicone', 'Braided Nylon'],
        'Bedding' => ['Cotton', 'Organic Cotton', 'Linen', 'Bamboo', 'Wool'],
        'Bath' => ['Cotton', 'Organic Cotton', 'Bamboo', 'Stainless Steel', 'Ceramic'],
        'Kitchen' => ['Stainless Steel', 'Cast Iron', 'Oak', 'Bamboo', 'Ceramic'],
        'Dining' => ['Ceramic', 'Stoneware', 'Porcelain', 'Glass', 'Stainless Steel', 'Linen'],
        'Decor' => ['Cotton', 'Linen', 'Wool', 'Ceramic', 'Glass', 'Oak'],
        'Lighting' => ['Brass', 'Glass', 'Oak', 'Ceramic', 'Aluminium'],
        'Camping' => ['Ripstop Nylon', 'Polyester', 'Aluminium', 'Stainless Steel'],
        'Hiking' => ['Ripstop Nylon', 'Aluminium', 'Carbon Fibre', 'Merino Wool'],
        'Garden' => ['Terracotta', 'Galvanised Steel', 'Stainless Steel', 'Recycled Plastic', 'Oak'],
        'Outdoor Furniture' => ['Teak', 'Aluminium', 'Rattan', 'Canvas'],
        'Grilling' => ['Cast Iron', 'Stainless Steel', 'Enamelled Steel'],
        'Water Sports' => ['Neoprene', 'Nylon', 'Silicone', 'Polyester'],
        'Fitness Equipment' => ['Cast Iron', 'Rubber', 'Neoprene', 'Foam', 'Steel'],
        'Cycling' => ['Polycarbonate', 'Aluminium', 'Steel', 'Polyester'],
        'Running' => ['Polyester', 'Neoprene', 'Nylon', 'Merino Wool'],
        'Team Sports' => ['Synthetic Leather', 'Rubber', 'Polyester', 'Foam'],
        'Racket Sports' => ['Graphite', 'Carbon Fibre', 'Aluminium', 'Felt'],
        'Swimming' => ['Recycled Nylon', 'Silicone', 'Polyester', 'Foam'],
    ];

    /**
     * Subcategories whose products have no size even in a sized department.
     */
    public const UNSIZED = ['Shoe Care', 'Kids Accessories', 'School'];

    /**
     * Final words that are already singular in use ("a pair of jeans").
     */
    private const PLURAL_ONLY = [
        'Jeans', 'Shorts', 'Leggings', 'Pants', 'Trousers', 'Headphones', 'Earbuds', 'Sunglasses', 'Goggles', 'Flats',
        'Shoes', 'Boots', 'Socks', 'Briefs', 'Trunks', 'Chinos', 'Earrings', 'Fins', 'Guards', 'Gloves', 'Flops',
        'Sneakers', 'Runners', 'Sandals', 'Espadrilles', 'Oxfords', 'Loafers', 'Brogues', 'Pumps', 'Insoles', 'Shoelaces',
        'Dumbbells', 'Kettlebells', 'Joggers', 'Pajamas',
    ];

    /**
     * Every leaf category with its path and the noun its products use.
     *
     * @return list<array{department: string, departmentIndex: int, sub: string, leaf: string, noun: string,
     *     size: ?string}>
     */
    public static function leaves(): array
    {
        $leaves = [];
        $departmentIndex = 0;
        foreach (self::DEPARTMENTS as $department => $data) {
            foreach ($data['children'] as $sub => $names) {
                foreach ($names as $name) {
                    [$leaf, $noun] = str_contains($name, '|') ? explode('|', $name, 2) : [$name, self::singular($name)];
                    $leaves[] = [
                        'department' => $department,
                        'departmentIndex' => $departmentIndex,
                        'sub' => $sub,
                        'leaf' => $leaf,
                        'noun' => $noun,
                        'size' => in_array($sub, self::UNSIZED, true) ? null : $data['size'],
                    ];
                }
            }
            $departmentIndex++;
        }

        return $leaves;
    }

    public static function singular(string $name): string
    {
        $words = explode(' ', $name);
        $last = array_pop($words);
        if (!in_array($last, self::PLURAL_ONLY, true)) {
            $last = match (true) {
                str_ends_with($last, 'ies') => substr($last, 0, -3) . 'y',
                (bool) preg_match('/(ss|ch|sh|x)es$/', $last) => substr($last, 0, -2),
                str_ends_with($last, 's') => substr($last, 0, -1),
                default => $last,
            };
        }
        $words[] = $last;

        return implode(' ', $words);
    }

    public static function slug(string $text): string
    {
        $text = strtolower(str_replace(['&', '/'], [' and ', ' '], $text));

        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $text), '-');
    }
}
