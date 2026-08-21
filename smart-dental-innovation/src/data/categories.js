// Categories are DB-only — fetched from the API (see useApiData.js / useHomeData.js).
// Empty so imports resolve; the derived exports below stay valid offline.
export const categories = [];

// For filter sidebars — uses `title` as label (id maps to product.category)
export const categoryFilters = categories.map((c) => ({ id: c.id, label: c.title }));

// Home grid shows only those with images
export const categoryTiles = categories.filter((c) => c.img);
