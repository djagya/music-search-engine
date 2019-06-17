export interface SearchResponse {
  total: Total;
  maxScore: number;
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
  data: any;
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
  page: number;
  pageSize: number;
  rows: any[];
}

export interface Total {
  value: number;
  relation: string;
}
