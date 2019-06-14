import axios, { AxiosResponse } from 'axios';
import { ChartResponse, ErrorResponse, RelatedResponse, SearchResponse, SelectedFields } from './types';

let useAws = false;

export function fetchSuggestions(
  field: string,
  value: string,
  selectedFields: SelectedFields,
): Promise<SearchResponse | ErrorResponse> {
  return axios
    .get('/api/typing', {
      params: {
        field,
        query: value.trim(),
        selected: JSON.stringify(getSelectedFieldsData(selectedFields)),
        meta: 0,
        aws: useAws,
      },
    })
    .then((res: AxiosResponse<SearchResponse>) => res.data)
    .catch(err => {
      console.log(err);
      return { error: JSON.stringify(err) } as ErrorResponse;
    });
}

export function fetchRelatedSuggestions(
  emptyFields: string[],
  selectedFields: SelectedFields,
): Promise<RelatedResponse | ErrorResponse> {
  return axios
    .get('/api/related', {
      params: {
        empty: emptyFields.join(':'),
        selected: JSON.stringify(getSelectedFieldsData(selectedFields)),
        meta: 0,
        aws: useAws,
      },
    })
    .then((res: AxiosResponse<RelatedResponse>) => res.data)
    .catch(err => {
      console.log(err);
      return { error: JSON.stringify(err) } as ErrorResponse;
    });
}

export function fetchChartRows(params: any, page: number): Promise<ChartResponse | ErrorResponse> {
  return axios
    .get('/api/chart', {
      params: {
        ...params,
        page,
        meta: 0,
        aws: useAws,
      },
    })
    .then((res: AxiosResponse<ChartResponse>) => res.data)
    .catch(err => {
      console.log(err);
      return { error: JSON.stringify(err) } as ErrorResponse;
    });
}

interface RequestData {
  [k: string]: string;
}

function getSelectedFieldsData(selectedFields: SelectedFields): RequestData {
  // Map of {fieldName: selected value}
  return Object.keys(selectedFields).reduce((res: RequestData, k: string) => {
    if (selectedFields[k]) {
      res[k] = selectedFields[k]!.value;
    }
    return res;
  }, {});
}

// todo: for now this hacky "singleton"
export function setUseAws(use: boolean = false) {
  useAws = use;
}
