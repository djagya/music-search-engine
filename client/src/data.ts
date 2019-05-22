import axios, { AxiosResponse } from 'axios';
import { ErrorResponse, SearchResponse, SelectedFields } from './types';

export function fetchSuggestions(
  field: string,
  value: string,
  selectedFields: SelectedFields,
): Promise<SearchResponse | ErrorResponse> {
  return axios
    .get('/typing', {
      params: {
        field,
        query: value.trim(),
        selected: JSON.stringify(getSelectedFieldsData(selectedFields)),
        meta: 0,
      },
    })
    .then((res: AxiosResponse<SearchResponse>) => {
      return res.data;
    })
    .catch(err => {
      console.log(err);
      return { error: JSON.stringify(err) } as ErrorResponse;
    });
}

export function fetchRelatedSuggestions(
  emptyFields: string[],
  selectedFields: SelectedFields,
): Promise<SearchResponse | ErrorResponse> {
  return axios
    .get('/related', {
      params: {
        empty: emptyFields.join(':'),
        selected: JSON.stringify(getSelectedFieldsData(selectedFields)),
        meta: 0,
      },
    })
    .then((res: AxiosResponse<SearchResponse>) => {
      console.log(res);
      return res.data;
    })
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
