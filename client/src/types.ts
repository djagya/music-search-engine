export interface SearchResponse {
  total: Total;
  took: number;
  suggestions: Suggestion[];
}

export interface Suggestion {
  value: string;
  score: number;
  count: number;

  data?: { [attr: string]: string };

  // Questionable fields.
  id: string;
  _index?: string;
}

export interface RelatedResponse {
  fields: { [field: string]: SearchResponse };
  data: Song[];
}

export interface SelectedFields {
  [name: string]: Suggestion | null;
}

export interface ErrorResponse {
  error: string;
}

export interface ChartResponse {
  total: Total;
  took: number;
  pagination: {
    page: number;
    pageSize: number;
    after: string | null;
    prev: string | null;
    sort: string | null;
  };
  rows: any[];
}

export interface Total {
  value: number;
  relation: string;
}

export interface Song {
  _index: string;
  _id: string;

  // Main fields.
  artist_name: string;
  release_title: string;
  song_name: string;

  // Metadata.
  release_genre: string | null;
  release_various_artists: number;
  release_year_released: number | null;
  release_upc: string | null;
  label_name: string | null;
  cover_art_url: string | null;
  release_medium: string | null;
  song_isrc: string | null;
  song_duration: number | null;

  // Spins only.
  id?: number;
  spin_timestamp?: string;
  // Epf only.
  song_id?: number;
  artist_id?: number;
  collection_id?: number;
}
