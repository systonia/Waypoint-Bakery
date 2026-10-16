-- Products, opening hours, carts and orders: what the storefront needs
CREATE TABLE products (
    id serial PRIMARY KEY,
    slug text NOT NULL UNIQUE,
    name jsonb NOT NULL,                 -- {"en": "...", "de": "..."}
    description jsonb NOT NULL,
    category text NOT NULL,
    price_cents integer NOT NULL CHECK (price_cents > 0),
    stock integer NOT NULL CHECK (stock >= 0),
    picture text NOT NULL,               -- a file in public/products
    position integer NOT NULL DEFAULT 0
);

-- One row per weekday (1 = Monday ... 7 = Sunday); a missing day is a closed day
CREATE TABLE opening_hours (
    weekday integer PRIMARY KEY CHECK (weekday BETWEEN 1 AND 7),
    opens time NOT NULL,
    closes time NOT NULL
);

-- A browser's cart, kept across pages under the id in its cart cookie
CREATE TABLE cart_lines (
    cart uuid NOT NULL,
    product_id integer NOT NULL REFERENCES products ON DELETE CASCADE,
    quantity integer NOT NULL CHECK (quantity > 0),
    updated_at timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (cart, product_id)
);

CREATE TABLE orders (
    id serial PRIMARY KEY,
    code text NOT NULL UNIQUE,           -- what the customer's link carries
    customer_name text NOT NULL,
    email text NOT NULL,
    pickup_date date NOT NULL,
    locale text NOT NULL,
    status text NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'received', 'baking', 'ready', 'picked_up', 'cancelled')),
    total_cents integer NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX orders_open ON orders (status) WHERE status NOT IN ('picked_up', 'cancelled');

CREATE TABLE order_lines (
    order_id integer NOT NULL REFERENCES orders ON DELETE CASCADE,
    product_id integer NOT NULL REFERENCES products,
    name text NOT NULL,                  -- as it was called when ordered, in the customer's language
    price_cents integer NOT NULL,
    quantity integer NOT NULL CHECK (quantity > 0),
    PRIMARY KEY (order_id, product_id)
);
