import Axios from 'axios'
import { message } from 'ant-design-vue'
import _trimStart from 'lodash/trimStart'
import {
  debounceMsg,
  getFirstError,
  handleValidateErrors,
  showLoginModal,
} from '@/libs/utils'
import _forIn from 'lodash/forIn'

const config = {
  baseURL: '/admin-api',
  timeout: 30 * 1000,
}

const axios = Axios.create(config)
const CancelToken = Axios.CancelToken

export const requestQueue = {}
const destroyUrlFromQueue = (path) => {
  if (!path) {
    return
  }
  path = path.slice('/admin-api/'.length)
  delete requestQueue[path]
}

const showError = res => {
  const { message: msg } = res.data
  msg && message.error(msg)
}

export const cancelAllRequest = (msg = '') => {
  Object.values(requestQueue).forEach(i => i.source.cancel(msg))
  _forIn(requestQueue, (value, url) => {
    value.source.cancel(msg)
    delete requestQueue[url]
  })
}

axios.interceptors.request.use(
  (config) => {
    const source = CancelToken.source()
    config.cancelToken = source.token

    requestQueue[_trimStart(config.url, '/')] = {
      source,
    }

    // 请求前清空表单验证错误
    if (config.validationForm) {
      config.validationForm[config.validationErrorKey] = {}
    }

    return config
  },
  (error) => {
    return Promise.reject(error)
  },
)

axios.interceptors.response.use(
  (res) => {
    destroyUrlFromQueue(res.config.url)
    return res
  },
  (err) => {
    const { response: res } = err
    const { config } = err

    // 可通过 config.disableHandle错误码 来禁用该类型错误的默认处理
    if (res && !config[`disableHandle${res.status}`]) {
      switch (res.status) {
        case 404:
          message.error('请求的网址不存在')
          break
        case 401:
          cancelAllRequest('登录失效: ' + config.url)
          message.error('登录已失效，请重新登录')
          if (!config.disableLoginModal) {
            showLoginModal()
          }

          break
        case 400:
          showError(res)
          break
        case 403:
          showError(res)
          cancelAllRequest('无权访问: ' + config.url)
          break
        case 422:
          if (config.showValidationMsg) { // 如果显示验证消息，则显示首条
            message.error(getFirstError(res))
          } else if (config.validationForm) { // 如果传入了 Vue 实例，则维护实例中的 errors
            config.validationForm[config.validationErrorKey] = handleValidateErrors(res)
          } // 否则不做处理
          break
        case 429:
          message.error('操作太频繁，请稍后再试')
          cancelAllRequest('操作太频繁')
          break
        default:
          message.error(`服务器异常(code: ${res.status})`)
          break
      }
    } else if (!res) {
      if (err instanceof Axios.Cancel) { // 手动取消时，err 为一个 Cancel 对象，有一个 message 属性
        console.log(err.toString())
      } else {
        debounceMsg('请求失败')
      }
    }

    config && destroyUrlFromQueue(config.url)

    return Promise.reject(err)
  },
)

export default class Request {
  method
  args = []
  config = {}
  defaultConfig = {
    showValidationMsg: false,
    validationForm: null,
    validationErrorKey: 'errors',
    disableLoginModal: false,
  }

  /**
   * 请求方法中，有 data 参数的，config 需要放到第三个位置
   * @type {string[]}
   */
  methodsWithData = [
    'put', 'patch', 'post',
  ]

  /**
   * @param {string} method
   * @param {any} args
   */
  constructor(method, args) {
    this.method = method
    this.args = Array.from(args)
  }

  then(resolve, reject) {
    const configPos = (this.methodsWithData.indexOf(this.method) !== -1) ? 2 : 1

    const args = this.args
    args[configPos] = Object.assign(
      {},
      args[configPos],
      this.defaultConfig,
      this.config,
    )

    axios[this.method](...args).then(resolve, reject)
  }

  /**
   * @param {defaultConfig} config
   * @return {Request}
   */
  setConfig(config) {
    this.config = config
    return this
  }

  /**
   * @param {string} url
   * @param {any} [config]
   * @return {Request}
   */
  static get(url, config) {
    return new Request('get', arguments)
  }

  /**
   * @param {string} url
   * @param {any} [config]
   * @return {Request}
   */
  static delete(url, config) {
    return new Request('delete', arguments)
  }

  /**
   * @param {string} url
   * @param {any} [data]
   * @param {any} [config]
   * @return {Request}
   */
  static post(url, data, config) {
    return new Request('post', arguments)
  }

  /**
   * @param {string} url
   * @param {any} [data]
   * @param {any} [config]
   * @return {Request}
   */
  static put(url, data, config) {
    return new Request('put', arguments)
  }

  /**
   * @param {string} url
   * @param {any} [data]
   * @param {any} [config]
   * @return {Request}
   */
  static patch(url, data, config) {
    return new Request('patch', arguments)
  }

  /**
   * @param config
   * @return {Request}
   */
  static request(config) {
    return new Request('request', arguments)
  }
}
