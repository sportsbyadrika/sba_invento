# Online Shop (e-commerce) and stock / pricing design

Status: **proposal for client review**. Nothing in this document is built yet.

This covers the client's requests from the 11-Nov-2026 meeting:

1. An online shop on its own URL: catalogue, cart, customer accounts, checkout, COD and e-payment, invoices, delivery tracking, returns and reviews.
2. How item prices, purchase costs and stock batches work when the same item is bought again at a different price.

---

## Part 1 — Prices, costs and stock batches

### 1.1 Rule: the item master describes the product, transactions decide the money

| Data | Where it lives | Changes when |
|---|---|---|
| Name, SKU, category, images, specifications, unit, HSN, tax rate | **Item master** (entered once) | Rarely |
| **Purchase cost** | Each **purchase receipt (GRN / bill) line**, stored as a **stock batch** | Every purchase, automatically |
| **Selling price** (shop, retail, wholesale) | **Price lists**, with effective dates | When the business decides. A new purchase can *suggest* a new price |
| Discount / offer price | Price-list **offer price** with start and end dates, or coupons | Campaigns |
| MRP (India, packaged goods) | **Batch**, because each lot can carry a different printed MRP | Every purchase |

The "Selling Price" and "Cost Price" fields on today's item screen become **defaults**. They are used only until the first purchase and price list exist. Nobody edits the item each time stock is bought.

### 1.2 Your example

| | Batch A (old) | Batch B (new) |
|---|---|---|
| Purchased | 50 @ ₹220 | 50 @ ₹250 |
| Planned selling price | ₹290 | ₹350 |
| Delivery charge | ₹30 | ₹10 |
| Still in stock | **15** | **50** |

The purchase creates batch B: 50 units at a cost of ₹250. The system then asks: *"New purchase price is higher. Update the shop price from ₹290 to ₹350?"* The user answers by choosing one of the two **pricing policies** below, set per item.

**Policy 1 — One current price (recommended; how Amazon and most shops work)**
- The customer sees **one price** and **65 available** (or fewer if an online cap is set, see 2.4).
- The user picks when ₹350 starts: *now*, *a set date*, or *when batch A is sold out*.

**Policy 2 — Batch-wise price**
- The shop sells the oldest batch first, at that batch's price.
- The customer sees **₹290 – only 15 left at this price**. After those 15 sell, the price becomes ₹350 automatically.
- Use this when the price legally follows the batch (India MRP), or for perishables or expiring goods.
- A single cart line can be split across batches; the invoice then shows two lines.

**What the business earns** when 20 units are sold (cost uses FIFO, oldest stock first):

| | Policy 1 (all at ₹350) | Policy 2 (15 × ₹290 + 5 × ₹350) |
|---|---|---|
| Sales | ₹7,000 | ₹6,100 |
| Cost of goods (FIFO: 15 × 220 + 5 × 250) | ₹4,550 | ₹4,550 |
| Gross profit | **₹2,450** | **₹1,550** |

**Stock valuation method.** This is a separate setting. It affects profit and stock value in the reports, not the price the customer sees.
- **FIFO:** the remaining stock keeps each batch's own cost.
- **Weighted average:** every unit costs (15 × 220 + 50 × 250) ÷ 65 = **₹243.08**.

**The delivery charge (₹30 / ₹10) can mean two different things. Please confirm which:**
- **Freight paid when buying (landed cost).**
  - It is added to that batch's cost.
  - If ₹30 is for the whole lot of 50: batch A costs 220 + 30/50 = ₹220.60 per unit, and batch B costs ₹250.20.
  - If it is per unit: ₹250 and ₹260.
  - The customer never sees it.
- **Delivery fee charged to the customer.**
  - This is not tied to a batch. It is set by **shipping rules**: per item, per weight, per area, or free above an order value. For example, "₹30 delivery, free above ₹999".
  - It shows at checkout and becomes its own line on the invoice.

### 1.3 Tables to add (inventory phase)

- `stock_batches`: item, warehouse, source document, batch no., qty in, qty remaining, unit cost (incl. landed cost), MRP, expiry, optional batch sell price
- `stock_moves`: every stock in or out (GRN, sale, return, adjustment, transfer), linked to a batch → stock ledger and valuation
- `price_lists` / `item_prices`: list (Online / Retail / Wholesale), item, price, compare-at (strike-through) price, offer price, valid from / to
- `stock_reservations`: quantity held by unpaid or undispatched online orders

---

## Part 2 — Online shop

### 2.1 Address

- Each company gets its own shop, either `https://shop.federaerp.com/<store>` or on its own domain (for example `https://store.clientname.com`).
- The shop is a **separate area** with its own customer logins. It is not part of the ERP login.

### 2.2 Customer side

| Area | Features |
|---|---|
| **Catalogue** | Category menu; search; filters on the **left**: category, price range, brand, rating, attributes (size, colour, material …), in-stock only, offers; sort by relevance, price, newest or rating; paged results |
| **Product page** | Image gallery (zoom); title; price with strike-through and discount %; stock badge ("In stock", "Only 3 left", "Out of stock"); key specifications; description; delivery estimate and charge for the customer's area; return policy; ratings and reviews; related items |
| **Registration** | Name, mobile number **with country code** (stored as +971…, +91…), e-mail, password and confirm password. E-mail verification. Optional mobile OTP (costs per SMS). Login with e-mail or mobile. Forgot password. Account lock after repeated wrong passwords |
| **Cart** | Works before login (guest cart), merged into the account at login. Quantity is limited by online stock and min/max per order. Shows price changes since the item was added |
| **Checkout** | Address book (several addresses, each labelled Home / Office / custom, with a default); delivery charge from shipping rules; coupon; payment method **COD** or **Online payment**; order summary with tax |
| **Payment** | Hosted payment page of the gateway (card, UPI, wallets, Apple Pay). Card data never touches our server. Confirmation comes from the gateway's signed **webhook**, not the browser redirect. Receipt page plus e-mail |
| **My orders** | Order list and details showing **the price paid at the time** (each line saves name, price, discount and tax). Delivery stage timeline, invoice PDF download, cancel (before dispatch), **request return**, **buy again** |
| **Reviews** | Only for **delivered** items the customer bought: 1–5 stars plus text (photos later). Published after admin approval |

### 2.3 Order stages

**Normal flow:** Placed → Payment confirmed or COD accepted → Packed → Shipped (courier and tracking number) → Out for delivery → Delivered.

**Cancellation:** a customer can cancel before dispatch. If paid online, the system refunds automatically.

**Return flow:** Return requested (reason, photos) → Approved or Rejected → Pick-up scheduled → Received and inspected → Refunded (to the original payment, or to the bank for COD) → stock returned to its batch, or written off as damaged.

Every stage change is recorded with time and user, shown to the customer, and e-mailed. SMS or WhatsApp can be added later.

### 2.4 Internal (ERP) side

- **Item → "Online" tab:**
  - **Publish on shop** (on/off, so an item can be managed internally but hidden online)
  - URL name; online title and description; images (several; ordered; main image; alt text; resized automatically)
  - Brand; filter attributes; weight and size for shipping
  - Min/max order quantity; return window (days)
  - **Online stock cap**, explained below
- **Online stock cap** (your "100 in stock, show only 10"):
  - The shop shows **the smallest of**: the cap (10), physical stock minus reservations, and the batch rule.
  - When stock drops below the cap, the shop shows the real lower number.
  - Alternative to confirm: a **fixed allocation**. Ten units are set aside for online and must be topped up by hand.
- **Online orders list**, using the existing list screen with filters, overview cards and keyboard use:
  - **Dispatch screen:** pick list, packing slip, generate invoice, courier and tracking number entry, bulk "mark shipped"
  - **Returns screen:** approve or reject, receive, refund
  - **Review moderation**
- **Shop settings:** logo, banners, home page sections, policies (terms, privacy, returns, shipping), shipping rules, COD rules (max order value, allowed areas, COD fee), payment gateway keys (stored encrypted), tax display.
- **Accounting (automatic, through the existing journal engine):**

| Event | Entry |
|---|---|
| Online payment | Dr Gateway clearing → Cr Customer |
| Gateway settlement | Dr Bank, Dr Gateway fees → Cr Gateway clearing |
| Invoice | Dr Customer → Cr Sales, Cr Output VAT/GST, Cr Delivery income |
| Cost of goods (from batches) | Dr Cost of goods sold → Cr Inventory |
| COD collected | Dr Cash / courier receivable → Cr Customer |
| Return | Credit note + stock back in |

- **Tax:** UAE VAT 5% invoice, or India GST (CGST+SGST or IGST chosen from the delivery state). India e-invoice / IRN applies to B2B only.

### 2.5 Payment gateways (choose per country)

| Country | Options | Notes |
|---|---|---|
| India | Razorpay, Cashfree, PayU | UPI, cards, net-banking; quick KYC |
| UAE | Network International (N-Genius), Telr, Checkout.com, Stripe | Card, Apple Pay; merchant account with a UAE bank or provider |

To approve a merchant account, gateways require visible **terms, privacy, refund and shipping policy** pages and the seller's contact details. India's e-commerce rules also require a grievance officer.

### 2.6 Delivery order

The shop needs real stock and costs, so the order changes. **Inventory + Purchases come before Sales invoices**:

| Step | Scope |
|---|---|
| **A** | Stock ledger, batches, GRN / purchase bill with landed cost, stock adjustments, price lists, FIFO / weighted-average valuation |
| **B** | Item "Online" tab: images, attributes, publish flag, online stock cap |
| **C** | Shop catalogue, product page, customer registration and login, cart |
| **D** | Checkout, address book, COD, gateway, order → invoice → journal posting, receipt and invoice e-mails |
| **E** | Dispatch screen, delivery stages, tracking, notifications |
| **F** | Returns and refunds, reviews, shop reports (sales by item, abandoned carts, top customers) |

### 2.7 Decisions needed from the client

1. **Shop address:** `shop.federaerp.com/<store>` or each client's own domain?
2. **Country and gateway:** India (Razorpay?), UAE (N-Genius / Telr?), or both?
3. **Pricing policy:** one current price (recommended) or batch-wise? And stock valuation: **FIFO** or **weighted average**?
4. **The ₹30 / ₹10 delivery charge:** purchase freight (part of cost) or a fee charged to the customer?
5. **Online stock:** a **cap** (recommended) or a fixed **allocation**?
6. **Mobile verification:** e-mail only at first, or SMS OTP (provider and cost)?
7. **Couriers:** enter the tracking number by hand first, or integrate one now (India: Shiprocket / Delhivery; UAE: Aramex)?
8. **Returns:** return window (e.g. 7 days) and refund method for COD orders?
